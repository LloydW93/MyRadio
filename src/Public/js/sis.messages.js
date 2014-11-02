/* Messages */
var Messages = function() {
    var highest_message_id = 0,
        unreadMessages = 0,
        glyphicons = ['question-sign', 'envelope', 'phone', 'globe'],
        table = document.createElement('table');

    table.setAttribute('class', 'messages');

    return {
        name: 'Messages',
        type: 'tab',
        initialise: function() {
            $(this).append(table);
        },
        update: function(data) {
            for (var i in data) {
                var locationStr,
                    img,
                    msgdate,
                    mins,
                    time,
                    read,
                    classes,
                    newRow;
                //Add the content dialog div
                locationStr = "";
                for (var l in data[i]['location']) {
                    locationStr = locationStr + '<br>'+data[i]['location'][l];
                }
                //Set some of the variables
                img = "<div class='glyphicon glyphicon-" + glyphicons[data[i]['type']] + "'></div>";
                msgdate = new Date(data[i]['time']*1000);
                mins = msgdate.getMinutes();
                if (mins < 10) {
                    mins = "0" + mins;
                }
                time = msgdate.getHours()+':'+mins+' '+msgdate.getDate()+'/'+(msgdate.getMonth()+1);
                read = "";
                classes = "";
                if (data[i]['read'] === false) {
                    classes = classes + " unread";
                    unreadMessages++;
                    this.setUnread(unreadMessages);
                }
                newRow = $('<tr class="td-msgitem'+classes+'" id="m'+data[i]['id']+'"><td>'+img+'</td><td>'+data[i]['title']+'</td><td>'+time+'</td></tr>');
                //Add the new row to the top of the messages table
                $(table).prepend(newRow);
                //Add the onclick handler for the new row
                var that = this, message = data[i];
                $(newRow).click(function() {
                    var id = $(this).attr('id').replace('m', '');
                    if ($(this).hasClass('unread')) {
                        //This is the first time the message has been opened. Mark as read
                        $.ajax({
                            url: myury.makeURL('SIS', 'messages.markread', {'id': id})
                        });
                        unreadMessages--;
                        that.setUnread(unreadMessages);
                        $(this).removeClass('unread');
                    }
                    myury.createDialog('Message', message['body']);
                });
                //Increment the highest message id, if necessary
                highest_message_id = (highest_message_id < message['id']) ? message['id'] : highest_message_id;
            }
            //Update the server's highest id parameter
            this.registerParam('messages_highest_id', highest_message_id);
        }
    }
}

sis.registerModule('messages', new Messages());
