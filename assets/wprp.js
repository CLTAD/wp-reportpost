function wprp_toggle(form_name, postID)
{
	jQuery(form_name).slideToggle(400);
	return false;
}

function wprp_report(form)
{
	// get POST DATA
	var post_ID=form.post.value;
	var reportas=form.report_as.value;
	var desc=form.description.value;
	var nonce=form._wpnonce.value;
	var comm_ID=form.comment.value;

	if (comm_ID >-1){

		// Hide All Other than Message
		jQuery('#wprp_report_link_'+post_ID+'_'+comm_ID).hide();
		jQuery('#wprpform'+post_ID+'_'+comm_ID).hide();
		jQuery('#wprp_message_'+post_ID+'_'+comm_ID).fadeIn(100);
		jQuery('#wprp_message_'+post_ID+'_'+comm_ID).html('<img src="' + wprpURL + '/assets/loading.gif" title="Processing your request, Please wait..." /> Processing your request, Please wait....');
		
		// Send Ajax
		jQuery.post(wprpURL + '/ajax.php', { postID: post_ID, commentID: comm_ID, report_as:reportas, description: desc, do_ajax_report:"true", wpnonce: nonce },
		  function(data){
			// Display the Return
			jQuery('#wprp_message_'+post_ID+'_'+comm_ID).html(data);
		  });
		
	} else {
		
		comm_ID = '-1';
		// Hide All Other than Message
		jQuery('#wprp_report_link_'+post_ID).hide();
		jQuery('#wprpform'+post_ID).hide();
		jQuery('#wprp_message_'+post_ID).fadeIn(100);
		jQuery('#wprp_message_'+post_ID).html('<img src="' + wprpURL + '/assets/loading.gif" title="Processing your request, Please wait..." /> Processing your request, Please wait....');
		
		// Send Ajax
		jQuery.post(wprpURL + '/ajax.php', { postID: post_ID, commentID: comm_ID, report_as:reportas, description: desc, do_ajax_report:"true", wpnonce: nonce },
		  function(data){
			// Display the Return
			jQuery('#wprp_message_'+post_ID).html(data);
		  });
	
	}
	
	return false;
}