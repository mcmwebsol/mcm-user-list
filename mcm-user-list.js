/**
 * used by role filter
 */
function roleFormOnsubmitHandler() {  

  // get params to pass in here 
  role = jQuery('#mcm-role').val();
  roleNonce = jQuery('#role_nonce').val();

  jQuery.get(
   mcmPassedInData.ajaxurl, 
   {
	'action':'role_filter',
	'role':role,
	'orderByField':'',
	'sortOrder':'',
	'_ajax_nonce':mcmPassedInData.nonce
   }, 
   function(response){
	 jQuery('#currentPageNumber').val(1); // reset to 1st page  
	 
	 // reset sort orders
	 document.getElementById('mcm-sort-by-desc-display-name').style.display = 'none';
	 document.getElementById('mcm-sort-by-desc-user-name').style.display = 'none';
	 
	 jQuery('#prevOrderBy').val(''); // reset order by field
	 
	 roleFormOnsubmitHandlerCallback(response); 
   }
  );
 } // end roleFormOnsubmitHandler()

 function roleFormOnsubmitHandlerCallback(response) {
 
   mcmCallbackHelper(response);
 
 } // end roleFormOnsubmitHandlerCallback() 
 
 /**
  * shared code for multiple callbacks
  */
 function mcmCallbackHelper(response) {
	 
   /*
	response like
	   array('users',
			 'num_results)
   */
 
   // update table here (#mcm-user-list-table-body)
   responseObj = JSON.parse(response);
   numResults = responseObj.num_results;
   users = responseObj.users;
   numUsers = users.length;
   usersTableBody = '';
   if (numResults) {
	 for (i = 0; i < numUsers; i++) { 
	   usersTableBody += '<tr><td>'+users[i].user_login+'</td><td>'+users[i].role+'</td><td>'+users[i].display_name+'</td></tr>';
	 }
   }
   jQuery('#mcm-user-list-table-body').html(usersTableBody);
   
   // update #mcm-num-results with numResults
   jQuery('#mcm-num-results').html(numResults);
   
   // update pagination links
   generatePagination(numResults, jQuery('#prevOrderBy').val(), jQuery('#mcm-role').val());	 
	 
 }	 
 
 /**
  * for orderBy onclick
  * 
  * @param {string} orderByField field to order by
  */
 function mcmOrderByFieldOnclickHandler(orderByField) { 
   // NOTE: orderByField will change onclick
   role = jQuery('#mcm-role').val();
   prevOrderBy = jQuery('#prevOrderBy').val();
   sortOrder = ( (prevOrderBy == '') ||
                 ( (orderByField == 'display_name') && (document.getElementById('mcm-sort-by-desc-display-name').style.display=='block') && (prevOrderBy == 'display_name') ) || 
                 ( (orderByField == 'user_login') && (document.getElementById('mcm-sort-by-desc-user-name').style.display=='block') && (prevOrderBy == 'user_login')  ) ||
                 ( (orderByField == 'display_name') && (prevOrderBy != 'display_name') ) || 
                 ( (orderByField == 'user_login') && (prevOrderBy != 'user_login') ) ) ? 
                '' : 
                 'DESC'; 
 
   if (sortOrder == 'DESC') {
     if (orderByField == 'display_name') { // show display name indicator, hide all others
       document.getElementById('mcm-sort-by-desc-user-name').style.display = 'none';   
       document.getElementById('mcm-sort-by-desc-display-name').style.display = 'block'; 
     }
     else { // show user name indicator, hide all others
       document.getElementById('mcm-sort-by-desc-display-name').style.display = 'none';  
       document.getElementById('mcm-sort-by-desc-role').style.display = 'none';
	   document.getElementById('mcm-sort-by-desc-user-name').style.display = 'block';   	   
     }	   
   }
   else { // hide all indicators
	   document.getElementById('mcm-sort-by-desc-display-name').style.display = 'none';  
	   document.getElementById('mcm-sort-by-desc-user-name').style.display = 'none'; 
   }	   	   
 
   jQuery.get(
    mcmPassedInData.ajaxurl, 
    {
	 /* params go here */
	 'action':'order_by_field',
	 '_ajax_nonce':mcmPassedInData.nonce,  
	 'role':role,
	 'orderByField':orderByField,
	 'sortOrder':sortOrder
    }, 
    function(response){
	 jQuery('#prevOrderBy').val(orderByField); 
	 mcmOrderByFieldOnclickHandlerCallback(response); 
    }
   );
   
   jQuery('#currentPageNumber').val(1); // reset to 1st page  
 
 } // end mcmOrderByFieldOnclickHandler()
 
 function mcmOrderByFieldOnclickHandlerCallback(response) {
 
   mcmCallbackHelper(response); 
 
 } // end mcmOrderByFieldOnclickHandlerCallback()
 
 /**
  * Generates pagination
  * 
  * @param {int} numResults the number of results found
  * @param {string} orderByField field to order by
  * @param {string} role
  */
 function generatePagination(numResults, orderByField, role) {
	  
    html = '<div class="mcm-pagination">';
	numPages = Math.ceil( numResults/( parseFloat(1*jQuery('#num_user_per_page').val()) ) ); 
	
    prevOrderBy = jQuery('#prevOrderBy').val();
	
	sortOrder = ( (prevOrderBy == '') ||
                 ( (orderByField == 'display_name') && (document.getElementById('mcm-sort-by-desc-display-name').style.display=='none') && (prevOrderBy == 'display_name') ) || 
                 ( (orderByField == 'user_login') && (document.getElementById('mcm-sort-by-desc-user-name').style.display=='none') && (prevOrderBy == 'user_login')  ) ||
                 ( (orderByField == 'display_name') && (prevOrderBy != 'display_name') ) || 
                 ( (orderByField == 'user_login') && (prevOrderBy != 'user_login') ) ) ? 
                '' : 
                 'DESC'; 
	
	currentPageNumber = 1 * ( jQuery('#currentPageNumber').val() );
	
	if ( numPages > 1 ) {
	    	    	    
	  if (currentPageNumber > 1) {
	    page = currentPageNumber;
	    html += '<li><a  href="#" onclick="mcmPaginationOnclick('+(currentPageNumber-1)+', \''+orderByField+'\', \''+role+'\', \''+sortOrder+'\')">Previous</a></li>'; // previous page link   
	  }  	    
	    	    
	    
	    
	  for (p = 1; p <= numPages; p++) { // generate pagination links (1..numPages)
	    if (p != currentPageNumber)      
	      html += '<li><a  href="#" onclick="mcmPaginationOnclick('+p+', \''+orderByField+'\', \''+role+'\', \''+sortOrder+'\')">'+p+'</a></li>';
	    else 	     
	      html += '<li>'+p+'</li>'; // already on this page, no need to link to it	     
	  } // end for
	  
	  if ( numResults > ( currentPageNumber * (1*jQuery('#num_user_per_page').val()) ) ) 	      
	    html += '<li><a  href="#" onclick="mcmPaginationOnclick('+(currentPageNumber+1)+', \''+orderByField+'\', \''+role+'\', \''+sortOrder+'\')">Next</a></li>'; // next page link here 	
    } 
	
	// update .mcm-users-pagination with html;
	jQuery('.mcm-users-pagination').html(html);
	  
  } // end generatePagination() 
 
 
 /**
  * pulls data for clicked page
  * 
  * @param {int} page page number for pagination
  * @param {string} orderByField field to order by
  * @param {string} role
  * @param {string} sortOrder order to sort in
  */
 function mcmPaginationOnclick(page, orderByField, role, sortOrder) {
      
   jQuery('#currentPageNumber').val(page);   
      
   jQuery.get(
    mcmPassedInData.ajaxurl, 
    {
	 /* params go here */
	 'action':'pagination',
	 'page':page,
	 '_ajax_nonce':mcmPassedInData.nonce,  
	 'role':role,
	 'orderByField':orderByField,
	 'sortOrder':sortOrder
    }, 
    function(response){
	   mcmCallbackHelper(response); 
    }
   );
   
 }
