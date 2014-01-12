<?php

/**
 * Provides the Metadata Common class for MyRadio
 * @package MyRadio_Core
 */

/**
 * The Metadata_Common class is used to provide common resources for
 * URY assets that utilise the Metadata system.
 *
 * The metadata system is a used to attach common attributes to an item,
 * such as a title or description. It includes versioning in the form of
 * effective_from and effective_to field, storing a history of previous values.
 *
 * @version 20130815
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @package MyRadio_Scheduler
 * @uses \Database
 *
 */
abstract class MyRadio_Metadata_Common extends ServiceAPI {
  use MyRadio_Creditable;
  use MyRadio_MetadataSubject;

  protected static $metadata_keys = array();
  protected $metadata;
  protected $credits = array();
  protected static $credit_names;

  /**
   * Gets the id for the string representation of a type of metadata
   */
  public static function getMetadataKey($string) {
    self::cacheMetadataKeys();
    if (!isset(self::$metadata_keys[$string])) {
      throw new MyRadioException('Metadata Key ' . $string . ' does not exist');
    }
    return self::$metadata_keys[$string]['id'];
  }

  /**
   * Gets whether the type of metadata is allowed to exist more than once
   */
  public static function isMetadataMultiple($id) {
    self::cacheMetadataKeys();
    foreach (self::$metadata_keys as $key) {
      if ($key['id'] == $id) {
        return $key['multiple'];
      }
    }
    throw new MyRadioException('Metadata Key ID ' . $id . ' does not exist');
  }

  protected static function cacheMetadataKeys() {
    if (empty(self::$metadata_keys)) {
      self::initDB();
      $r = self::$db->fetch_all('SELECT metadata_key_id AS id, name,'
              . ' allow_multiple AS multiple FROM metadata.metadata_key');
      foreach ($r as $key) {
        self::$metadata_keys[$key['name']]['id'] = (int) $key['id'];
        self::$metadata_keys[$key['name']]['multiple'] = ($key['multiple'] === 't');
      }
    }
  }

  protected static function getCreditName($credit_id) {
    if (empty(self::$credit_names)) {
      $r = self::$db->fetch_all('SELECT credit_type_id, name FROM people.credit_type');

      foreach ($r as $v) {
        self::$credit_names[$v['credit_type_id']] = $v['name'];
      }
    }

    return empty(self::$credit_names[$credit_id]) ? 'Contrib' : self::$credit_names[$credit_id];
  }

  /**
   * Sets a *text* metadata key to the specified value. Does not work for image metadata.
   *
   * If any value is the same as an existing one, no action will be taken.
   * If the given key has is_multiple, then the value will be added as a new, additional key.
   * If the key does not have is_multiple, then any existing values will have effective_to
   * set to the effective_from of this value, effectively replacing the existing value.
   * This will *not* unset is_multiple values that are not in the new set.
   *
   * @param String $string_key The metadata key
   * @param mixed $value The metadata value. If key is_multiple and value is an array, will create instance
   * for value in the array.
   * @param int $effective_from UTC Time the metavalue is effective from. Default now.
   * @param int $effective_to UTC Time the metadata value is effective to. Default NULL (does not expire).
   * @param String $table The metadata table, *including* the schema.
   * @param String $id_field The ID field in the metadata table.
   */
  public function setMeta($string_key, $value, $effective_from = null, $effective_to = null, $table = null, $id_field = null) {
    $meta_id = self::getMetadataKey($string_key); //Integer meta key
    $multiple = self::isMetadataMultiple($meta_id); //Bool whether multiple values are allowed
    if ($effective_from === null) {
      $effective_from = time();
    }

    //Check if value is different
    $current_meta = $this->getMeta($string_key);

    if ($multiple) {
      if (empty($current_meta)) {
        $current_meta = [];
      }
      // Normalise incoming value to be an array.
      if (!is_array($value)) {
        $value = [$value];
      }

      // Don't add existing metadata again.
      $all_values = $value;
      $value = array_diff($value, $current_meta);

      // Expire any metadata that is no longer current.
      // TODO: use only one query.
      foreach (array_diff($current_meta, $all_values) as $dead) {
        self::$db->query('UPDATE ' . $table . ' SET effective_to = $1
          WHERE metadata_key_id=$2 AND ' . $id_field . '=$3 AND metadata_value=$4
          AND (effective_to IS NULL OR effective_to > $1)',
          [
            CoreUtils::getTimestamp($effective_from),
            $meta_id,
            $this->getID(),
            $dead
          ]
        );
      }
    } else {
      //Not multiple key
      if (is_array($value)) {
        //Can't have an array for a single value
        throw new MyRadioException('Tried to set multiple values for a single-instance metadata key!');
      }
      if ($value == $current_meta) {
        //Value not changed
        return false;
      }
      //Okay, expire old value.
      self::$db->query('UPDATE ' . $table . ' SET effective_to = $1
        WHERE metadata_key_id=$2 AND ' . $id_field . '=$3', array(CoreUtils::getTimestamp($effective_from), $meta_id, $this->getID()));
    }

    // Bail out if we're about to insert nothing.
    if (!empty($value)) {
      $sql = 'INSERT INTO ' . $table
              . ' (metadata_key_id, ' . $id_field . ', memberid, approvedid, metadata_value, effective_from, effective_to) VALUES ';
      $params = array($meta_id, $this->getID(), MyRadio_User::getCurrentOrSystemUser()->getID(), CoreUtils::getTimestamp($effective_from),
          $effective_to == null ? null : CoreUtils::getTimestamp($effective_to));

      if (is_array($value)) {
        $param_counter = 6;
        foreach ($value as $v) {
          $sql .= '($1, $2, $3, $3, $' . $param_counter . ', $4, $5),';
          $params[] = $v;
          $param_counter++;
        }
        //Remove the extra comma
        $sql = substr($sql, 0, -1);
      } else {
        $sql .= '($1, $2, $3, $3, $6, $4, $5)';
        $params[] = $value;
      }

      self::$db->query($sql, $params);
    }

    if ($multiple && is_array($value)) {
      foreach ($value as $v) {
        if (!in_array($v, $this->metadata[$meta_id])) {
          $this->metadata[$meta_id][] = $v;
        }
      }
    } else {
      $this->metadata[$meta_id] = $value;
    }

    return true;
  }

  /**
   * Returns an Array of Arrays containing Credit names and roles, or just name.
   * @param boolean $types If true return an array with the role as well. Otherwise just return the credit.
   * @return type
   */
  public function getCreditsNames($types = true) {
    $return = array();
    foreach ($this->credits as $credit) {
      if ($types) {
        $credit['name'] = MyRadio_User::getInstance($credit['memberid'])->getName();
        $credit['type_name'] = self::getCreditName($credit['type']);
      } else {
        $credit = MyRadio_User::getInstance($credit['memberid'])->getName();
      }
      $return[] = $credit;
    }
    return $return;
  }

  /**
   * Get all credits
   * @param MyRadio_Metadata_Common $parent Used when there is inheritance enabled
   * for this object. In this case credits are merged.
   * @return type
   */
  public function getCredits($parent = null) {
    $parent = $parent === null ? [] : $parent->getCredits();
    $current = empty($this->credits) ? [] : $this->credits;
    return array_unique(array_merge($current, $parent), SORT_REGULAR);
  }

  /**
   * Similar to getCredits, but only returns the User objects. This means the loss of the credit type in the result.
   */
  public function getCreditObjects($parent = null) {
    $r = array();
    foreach ($this->getCredits($parent) as $credit) {
      $r[] = $credit['User'];
    }
    return $r;
  }

  /**
   * Gets the presenter credits for as a comma-delimited string.
   *
   * @return String
   */
  public function getPresenterString() {
    $str = '';
    foreach ($this->getCredits() as $credit) {
      if ($credit['type'] !== 1) {
        continue;
      } else {
        $str .= $credit['User']->getName().', ';
      }
    }

    return empty($str) ? '' : substr($str, 0, -2);
  }

  public function getMeta($meta_string) {
    return isset($this->metadata[self::getMetadataKey($meta_string)]) ?
      $this->metadata[self::getMetadataKey($meta_string)] : null;
  }

  /**
   * Updates the list of Credits.
   *
   * Existing credits are kept active, ones that are not in the new list are set to effective_to now,
   * and ones that are in the new list but not exist are created with effective_from now.
   *
   * @param MyRadio_User[] $users An array of Users associated.
   * @param int[] $credittypes The relevant credittypeid for each User.
   */
  public function setCredits($users, $credittypes, $table, $pkey) {
    //Start a transaction, atomic-like.
    self::$db->query('BEGIN');

    $newcredits = $this->mergeCreditArrays($users, $credittypes);
    $oldcredits = $this->getCredits();

    $this->removeOldCredits($oldcredits, $newcredits, $table, $pkey);
    $this->addNewCredits($oldcredits, $newcredits, $table, $pkey);
    $this->updateLocalCredits($newcredits);

    //Oh, and commit the transaction. I always forget this.
    self::$db->query('COMMIT');

    return $this;
  }

  /*
   * Merges two parallel credit arrays into one array of credits.
   *
   * @param array  $users  The array of incoming credit users.
   * @param array  $types  The array of incoming credit types.
   *
   * @return array The merged credit array.
   */
  private function mergeCreditArrays($users, $types) {
    return array_filter(
      array_map(
        function($user, $type) {
          return (empty($user) || empty($type))
          ? null
          : [ 'User' => $user, 'type' => $type, 'memberid' => $user->getID() ];
        },
        $users,
        $types
      ),
      function($credit) { return !empty($credit); }
    );
  }

  /**
   * De-activates any credits that are not in the incoming credits set.
   *
   * @param array  $old    The array of existing credits.
   * @param array  $new    The array of incoming credits.
   * @param string $table  The database table to update.
   * @param string $pkey   The primary key of the object to update.
   *
   * @return null Nothing.
   */
  private function removeOldCredits($old, $new, $table, $pkey) {
    foreach ($old as $credit) {
      if (!in_array($credit, $new)) {
        self::$db->query(
          'UPDATE '.$table.' SET effective_to=NOW()'
          . 'WHERE '.$pkey.'=$1 AND creditid=$2 AND credit_type_id=$3',
          [$this->getID(), $credit['User']->getID(), $credit['type']],
          true
        );
      }
    }
  }

  /**
   * Creates any new credits that are not in the existing credits set.
   *
   * @param array  $old    The array of existing credits.
   * @param array  $new    The array of incoming credits.
   * @param string $table  The database table to update.
   * @param string $pkey   The primary key of the object to update.
   *
   * @return null Nothing.
   */
  private function addNewCredits($old, $new, $table, $pkey) {
    foreach ($new as $credit) {
      //Look for an existing credit
      if (!in_array($credit, $old)) {
        //Doesn't seem to exist.
        self::$db->query(
          'INSERT INTO '.$table.' ('.$pkey.', credit_type_id, creditid, effective_from,'
          . 'memberid, approvedid) VALUES ($1, $2, $3, NOW(), $4, $4)',
          [
            $this->getID(),
            $credit['type'],
            $credit['memberid'],
            MyRadio_User::getCurrentOrSystemUser()->getID()
          ],
          true
        );
      }
    }
  }

  /**
   * Updates the local credits cache for this object.
   *
   * @param array  $new  The array of incoming credits
   * @param array  $types  The array of incoming credit types.
   *
   * @return null Nothing.
   */
  private function updateLocalCredits($new) {
    $this->credits = $new;
  }
}