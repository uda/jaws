/**
 * PrivateMessage Javascript actions
 *
 * @category    Ajax
 * @package     PrivateMessage
 * @author      Mojtaba Ebrahimi <ebrahimi@zehneziba.ir>
 * @copyright   2013 Jaws Development Group
 * @license     http://www.gnu.org/copyleft/lesser.html
 */
var PrivateMessageCallback = {
    ComposeMessage: function (response) {
        response = response[0];
        if (response.css !== 'notice-message' || response.css!== 'error-message') {
            console.log('error');
        }
        $('simple_response').set('html', response.message);
    },
    SaveDraftMessage: function (response) {
        response = response[0];
        if (response.css !== 'notice-message' || response.css!== 'error-message') {
            console.log('error');
        }
        $('simple_response').set('html', response.message);
    }
}


/**
 * Removes the attachment
 */
function removeAttachment(id) {
    $('frm_file').reset();
    $('btn_attach' + id).hide();
    $('file_link' + id).set('html', '');
    $('file_size' + id).set('html', '');
    $('btn_upload').show();
    $('attachment' + lastAttachment).show();
    uploadedFiles['file' + id] = false;
}

/**
 * Disables/Enables form elements
 */
function toggleDisableForm(disabled)
{
    $('subject').disabled         = disabled;
    $('body').disabled      = disabled;
    $('btn_back').disabled    = disabled;
    $('btn_save_draft').disabled = disabled;
    $('btn_send').disabled     = disabled;
}


/**
 * Uploads the attachment file
 */
function uploadFile() {
    var iframe = new Element('iframe', {id:'ifrm_upload', name:'ifrm_upload'});
    $('compose').adopt(iframe);
    $('attachment_number').value = lastAttachment;
    $('attachment' + lastAttachment).hide();
    $('attach_loading').show();
    toggleDisableForm(true);
    $('frm_file').submit();
}

/**
 * Sets the uploaded file as attachment
 */
function onUpload(response) {
    toggleDisableForm(false);
    console.warn(lastAttachment);
    uploadedFiles['file' + lastAttachment] = response.file_info;
    console.log(uploadedFiles);
    if (response.type === 'error') {
        alert(response.message);
        $('frm_file').reset();
        $('btn_upload').show();
        $('attachment' + lastAttachment).show();
    } else {
        $('file_link' + lastAttachment).set('html', response.file_info.user_filename);
        $('file_size' + lastAttachment).set('html', response.file_info.filesize_format);
        $('btn_attach' + lastAttachment).show();
        $('attachment' + lastAttachment).dispose();
        addFileEntry();
    }
    $('attach_loading').hide();
    $('ifrm_upload').destroy();
}

/**
 * add a file entry
 */
function addFileEntry() {
    lastAttachment++;
    var id = lastAttachment;

    entry = '<div id="btn_attach' + id + '"> <img src="gadgets/Contact/images/attachment.png"/> <a id="file_link' + id + '"></a> ' +
        ' <small id="file_size' + id + '"></small> <a onclick="javascript:removeAttachment(' + id + ');" href="javascript:void(0);">' +
        '<img border="0" title="Remove" alt="Remove" src="images/stock/cancel.png"></a></div>';
    entry += ' <input type="file" onchange="uploadFile();" id="attachment' + lastAttachment + '" name="attachment' + lastAttachment + '" size="1" style="display: block;">';

    $('attachment_addentry' + id).innerHTML = entry + '<span id="attachment_addentry' + (id + 1) + '">' + $('attachment_addentry' + id).innerHTML + '</span>';

    $('attach_loading').hide();
    $('btn_attach' + id).hide();
}
/**
 * get users list with custom term
 */
function getUsers(term) {
    return pmAjax.callSync('GetUsers', term);
}

/**
 * get groups list with custom term
 */
function getGroups(term) {
   return pmAjax.callSync('GetGroups', term);
}

/**
 * get groups list with custom term
 */
function composeMessage(id) {
    var data = new Array();
    data['id'] = $('id').value;
    data['parent'] = $('parent').value;
    data['recipient_users'] = $('recipient_users').value;
    data['recipient_groups'] = $('recipient_groups').value;
    data['subject'] = $('subject').value;
    data['body'] = $('body').value;
    data['published'] = true;
//    data['uploaded_files'] = uploadedFiles;
   pmAjax.callAsync('ComposeMessage', data);
}


/**
 * get groups list with custom term
 */
function saveDraft(id) {
    var data = new Array();
    data['id'] = id;
    data['parent'] = $('parent').value;
    data['recipient_users'] = $('recipient_users').value;
    data['recipient_groups'] = $('recipient_groups').value;
    data['subject'] = $('subject').value;
    data['body'] = $('body').value;
    data['published'] = false;
    data['uploaded_files'] = uploadedFiles;
    pmAjax.callAsync('SaveDraftMessage', data);
}

var pmAjax = new JawsAjax('PrivateMessage', PrivateMessageCallback);
var uploadedFiles = new Array();
var lastAttachment = 1;