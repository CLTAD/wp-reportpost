<?php
/*
	# REPORT POST CLASS
	This class handles all Requests to Database
	Table Names
		wpreport
		wpreport_comments
		wpreport_archive
*/
class ReportPost
{
	
	var $last_error;
	
	var $totalRows;
	
	var $insert_id;
	
	# Construct
	function ReportPost()
	{
		$this->last_error = '';
		$this->totalRows = 0;
		$this->insert_id=0;
	}
	
	# Function to Add New Report to Database
	function add($postID, $type, $comment, $commentID, $stamp = '', $ip='')
	{
		
		global $wpdb, $blog_id;
		// EMPTY
		if(empty($stamp)){
			$stamp = time();
		}
		// CHECK For Existing Report
		
		
		$sql = "SELECT `id` FROM `".$wpdb->base_prefix."wpreport` WHERE `blogID` = %s AND `postID` = %s AND `status` != 2 LIMIT 1";
		
		$sql = $wpdb->prepare($sql, $blog_id, $postID);
		
		$reportID = $wpdb->get_var($sql);
		$this->insert_id = $reportID;
		
		if(!$reportID || !is_numeric($reportID) || $reportID <= 0) // Add New to Get Report ID
		{
			// Get the POST
			$post = get_blog_post($blog_id, $postID); // This uses Wordpress Functionality
			if(!$post || $post->ID <= 0)
			{
				$this->last_error = "Unable to Retrieve Post Details!";
				return false;
			}
			
			// Add New Report
			
			$sql = "INSERT INTO `".$wpdb->base_prefix."wpreport` (`blogID`,`postID`,`commentID`,`post_title`,`stamp`,`status`) VALUES (%s,%s,%s,%s,%s,%s)";
			
			$sql = $wpdb->prepare($sql, $blog_id, $postID, $commentID, $post->post_title, $stamp, 1);
			
			$wpdb->query($sql);
			
			// Check for Error and Insert Success
			
			if($wpdb->rows_affected <= 0)
			{
				$this->last_error = "Error, Unable to Add New Report : " . $wpdb->last_error;
				return false;
			}
			
			// Assign new reportID
			$reportID = $wpdb->insert_id;
			
			$this->insert_id = $reportID;
			
			$sendMail='';
					
			if(intval(get_site_option("rp_send_email")) > 0){
				$sendMail = get_site_option("rp_email_address");
			}
			// Send Email
			if(!empty($sendMail))
				$this->sendMail($sendMail, $post->post_title, $type, $comment);
		}
		// CHK IP provided
		if(empty($ip))
			$ip = $this->get_ipaddress();
		
		// Add Comment & Type to the Next Table
		
		$sql = "INSERT INTO `".$wpdb->base_prefix."wpreport_comments`(`reportID`,`type`,`comment`,`ip`) VALUES(%s,%s,%s,%s);";
		
		$sql = $wpdb->prepare($sql, $reportID, $type, $comment, $ip);
		
		$wpdb->query($sql);
			
		// Check for Error and Insert Success
		
		if($wpdb->rows_affected <= 0)
		{
			$this->last_error = "Error, Unable to Add New Report : " . $wpdb->last_error;
			return false;
		}
		
		return true; // A Sucess
	}
	
	
	# Add Archive
	function archive($reportID, $userID, $comment)
	{
		global $wpdb;
		
		// Check if Report Exists
		$sql = "SELECT COUNT(*) FROM `".$wpdb->base_prefix."wpreport` WHERE `id` = %s AND status=1";
		
		$sql = $wpdb->prepare($sql, $reportID);
		
		$count = $wpdb->get_var($sql);
		
		if(!$count || !is_numeric($count) || $count <= 0 )
		{
			$this->last_error = 'Unable to find Report Specified To Archive';
			return false;
		}
		
		// CHECK if Archive EXISTS
		$sql = "SELECT COUNT(*) FROM `".$wpdb->base_prefix."wpreport_archive` WHERE `reportID` = %s";
		
		$sql = $wpdb->prepare($sql, $reportID);
		
		$count = $wpdb->get_var($sql);
		
		if(!$count || !is_numeric($count) || $count <= 0 ) // Add Archive Record
		{
			// get blog id first
		    $sql = "SELECT blogID FROM `".$wpdb->base_prefix."wpreport` WHERE `id` = %s";		    
		    $sql = $wpdb->prepare($sql, $reportID);		    
		    $blogid = $wpdb->get_var($sql);
		    if (!isset($blogid)){
		        $this->last_error = "Unable to retrieve blog referenced in report : " . $wpdb->last_error;
		        return false;
		    }		    
		    
		    $sql = "INSERT INTO `".$wpdb->base_prefix."wpreport_archive` (`reportID`,`blogID`, `moderatorID`,`comment`,`stamp`,`ip`) VALUES (%s,%s,%s,%s,%s,%s);";
			$sql = $wpdb->prepare($sql, $reportID, $blogid, $userID, $comment, time(), $this->get_ipaddress());			
			$wpdb->query($sql);
			
			// Check for Error and Insert Success
			
			if($wpdb->rows_affected <= 0)
			{
				$this->last_error = "Error 1, Unable to Archive Report : " . $wpdb->last_error;
				return false;
			}
		} // IF EXISTS
		
		// Update Status of Reports
		
		$sql = "UPDATE `".$wpdb->base_prefix."wpreport` SET `status` = 2 WHERE `id` = %s";
		
		$sql = $wpdb->prepare($sql, $reportID);
		
		$wpdb->query($sql);
			
		// Check for Error and Update
		
		if($wpdb->rows_affected <= 0 && !empty($wpdb->last_error))
		{
			$this->last_error = "Error 2, Unable to Archive Report : " . $wpdb->last_error;
			return false;
		}
		
		return true;
	}
	
	function delete($reportID)
	{
		
		global $wpdb;
		// Delete as Requested From reports & archives
		
		# Delete From archive
		$sql = "DELETE FROM `".$wpdb->base_prefix."wpreport_archive` WHERE `reportID` = %s;";
		
		$sql = $wpdb->prepare($sql, $reportID);
		
		$wpdb->query($sql);
			
		// Check for Error and Update
		
		if($wpdb->rows_affected <= 0 && !empty($wpdb->last_error))
		{
			$this->last_error = "Error, Unable to Delete Report : " . $wpdb->last_error;
			return false;
		}
		
		# Delete From Comments
		$sql = "DELETE FROM `".$wpdb->base_prefix."wpreport_comments` WHERE `reportID` = %s;";
		
		$sql = $wpdb->prepare($sql, $reportID);
		
		$wpdb->query($sql);
			
		// Check for Error and Update
		
		if($wpdb->rows_affected <= 0 && !empty($wpdb->last_error))
		{
			$this->last_error = "Error, Unable to Delete Report : " . $wpdb->last_error;
			return false;
		}
		
		# DELETE FROM REPORT
		$sql = "DELETE FROM `".$wpdb->base_prefix."wpreport` WHERE `id` = %s;";
		
		$sql = $wpdb->prepare($sql, $reportID);
		
		$wpdb->query($sql);
			
		// Check for Error and Update
		
		if($wpdb->rows_affected <= 0 && !empty($wpdb->last_error))
		{
			$this->last_error = "Error, Unable to Delete Report : " . $wpdb->last_error;
			return false;
		}
		
		
		return true; // Finally 
	}
	
	# Get List of Reports
	function findReports($order = '', $limit = 20, $where = '', $offset = 0 )
	{		
		global $wpdb;
		
		$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM `".$wpdb->base_prefix."wpreport` $where $order LIMIT %d, %d;";
		
		$sql = $wpdb->prepare($sql, $offset, $limit);
		
		$results = $wpdb->get_results($sql, OBJECT);
		
		$sql = "SELECT FOUND_ROWS();";
		
		$this->totalRows = $wpdb->get_var($sql);
		
		if($this->totalRows <= 0 )
			return NULL;
		
		return $results;
		
	}
	
	# Get Comments
	function getComments($reportID)
	{
		global $wpdb;
		
		$sql = "SELECT * FROM `".$wpdb->base_prefix."wpreport_comments` WHERE `reportID` = %s";
		
		$sql = $wpdb->prepare($sql, $reportID);
		
		return $wpdb->get_results($sql, OBJECT);
	}
	
	# Get Archives for Report
	function getArchive($reportID)
	{
		global $wpdb;
		
		$sql = "SELECT * FROM `".$wpdb->base_prefix."wpreport_archive` WHERE `reportID` = %s";
		
		$sql = $wpdb->prepare($sql, $reportID);
		
		return $wpdb->get_results($sql, OBJECT);
	}
	
	# Get List of Archives
	function findArchives($order = '', $limit = 20, $where = '', $offset = 0 )
	{
		global $wpdb;
		
		$sql = "SELECT SQL_CALC_FOUND_ROWS r.postID, r.post_title, r.stamp as report_date, a.*, r.commentID ";
		$sql .= " FROM `".$wpdb->base_prefix."wpreport_archive` a LEFT JOIN `".$wpdb->base_prefix."wpreport` r ON (r.id = a.reportID) ";
		$sql .= " $where  $order LIMIT %d,%d";
		
		$sql = $wpdb->prepare($sql, $offset, $limit);
		
		$results = $wpdb->get_results($sql, OBJECT);
		
		$sql = "SELECT FOUND_ROWS();";
		
		$this->totalRows = $wpdb->get_var($sql);
		
		if($this->totalRows <= 0){
			return NULL;
		}
		
		return $results;
	}
	
	# Get IP of USER
	function get_ipaddress() {
		if (empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
			$ip_address = $_SERVER["REMOTE_ADDR"];
		} else {
			$ip_address = $_SERVER["HTTP_X_FORWARDED_FOR"];
		}
		if(strpos($ip_address, ',') !== false) {
			$ip_address = explode(',', $ip_address);
			$ip_address = $ip_address[0];
		}
		return $ip_address;
	}
	
	# Send Mail
	function sendMail($to, $post_title, $type, $comment)
	{
		$body = '<div>
		<h2>! Report</h2>
		<p><strong>'.$post_title.'</strong> has been reported in <a href="'.get_bloginfo('wpurl').'"><em>'.get_bloginfo('name').'</em></a> as <strong><em>'.$type.'</em></strong>. User left the following comments:</p>
		<p> -- <br>'.$comment.'<br>--</p>
		<p> More reports and and comments on this particular post and others can be views in admin area.</p>
		<p>* You will not receive further notification for this Post until it has been archived or deleted. However All Feature reports will be logged and can be viewed in Report Section.</p>
		<p><br>Report: </p>
		<table border="0" cellpadding="3" cellspacing="0" width="100%">
				<tr>
					<td align="left" valign="top">Post Title:</td>
					<td align="left" valign="top">'.$post_title.'</td>
				</tr>
				<tr>
					<td align="left" valign="top">Reported: </td>
					<td align="left" valign="top">'.$type.'</td>
				</tr>
				<tr>
					<td align="left" valign="top">Comments: </td>
					<td align="left" valign="top">'.$comment.'</td>
				</tr>
			</table>
		</div>';
		
		$headers = "MIME-Version: 1.0" . "\r\n";
		$headers .= "Content-type:text/html;charset=iso-8859-1" . "\r\n";
		
		// More headers
		$headers .= 'From: '.get_bloginfo('name').'<'.get_bloginfo('admin_email').'>' . "\r\n";
		
		// Send EMAIL
		wp_mail($to, "[Report] ".$post_title,$body,$headers);
	}
} // Class
?>