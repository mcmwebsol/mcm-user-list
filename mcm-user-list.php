<?php
/*
Plugin Name: User List
Plugin URI: 
Description: Implements a shortcode [mcm-user-list] for displaying and filtering users
Version: 1.2
Author: MCM Web Solutions, LLC (Joseph McMurry)
Author URI: https://mcmwebsite.com
Text Domain: mcm-user-list                                                                                                                                                        
License: GPLv2 


Note: Assumes that the site is not on WP Multi-site 
 */
if ( !defined('ABSPATH') ) exit; // Exit if accessed directly 

$mcmUserList = new MCM_User_List();

/**
 * Used to create a list of users
 */
class MCM_User_List {
	
  /**
   * Maximum number of users displayed per page
   */
  const MCM_NUM_USERS_PER_PAGE = 10;
  
  /**
   * String for nonce
   */
  const MCM_USER_LIST_ROLE_NONCE = 'this-is-the-mcm-user-list-role-nonce';
	
  /**
   * Shortcode string
   */	
  private static $shortCodeStr = 'mcm-user-list';	
	
  /**
   * Constructor
   * 
   * @param void None
   * 
   * @return void
   */ 	
  function __construct() { 	     	  
    add_shortcode( self::$shortCodeStr, array(&$this, 'shortCode' ) ); // for shortcode

    add_action('wp_ajax_role_filter', array(&$this, 'mcm_user_list_ajax_role_filter') );   
    
    add_action('wp_ajax_order_by_field', array(&$this, 'mcm_user_list_ajax_order_by_field') ); 
    
    add_action('wp_ajax_pagination', array(&$this, 'mcm_user_list_ajax_pagination') ); 

    add_action( 'wp_enqueue_scripts', array(&$this, 'enqueueStyleAndScript') ); //  enqueue stylesheet and JS
  } // end __construct()


  /**
   * Enqueue stylesheet and JS
   * 
   * @param void None
   * 
   * @return void
   */ 
  function enqueueStyleAndScript() {  
    wp_enqueue_style( 'mcm-user-list-style', plugins_url('style.css', __FILE__) );
    
    wp_enqueue_script( 'mcm-user-list-script', plugins_url('mcm-user-list.js', __FILE__), array('jquery') );
    
    // pass in ajaxurl
    wp_localize_script( 'mcm-user-list-script', 'mcmPassedInData', array( 
                           'ajaxurl' => admin_url( 'admin-ajax.php' ),
                           'nonce' => wp_create_nonce(self::MCM_USER_LIST_ROLE_NONCE) ) );
  } // end enqueueStyle()
  
  
  /** 
   * Get all user roles
   *  
   * @param void None
   * 
   * @return array all user roles
   */ 
  private function getAllRoles() {
	
	global $wp_roles;
	
	$roles = $wp_roles->get_names();
	
	return $roles;
	
  } // end getAllRoles()


  /**
   * Sanitize/only allow proper values for role
   *   
   * @param string $role
   * 
   * @return array role if valid
   */ 
  private function validateRole($role) {
	 
	  $ret = '';
	  
	  $roleKeys = array_keys( $this->getAllRoles() );
	  	  
	  if ( in_array($role, $roleKeys ) ) 
		$ret = $role;	  	  	  
	  
	  return $ret;  
	  
  } // end validateRole()	  
  
  
  /**
   * Logging functionality tied into WP_DEBUG_LOG setting - logs to a file if WP_DEBUG is true
   *     
   * @param string $msg the message to log
   * 
   * @return void
   */ 
  private function mcmLog($msg) {
   
    if (WP_DEBUG_LOG) {
	    // Log  to a file
	    $msg = '['.date('Y-m-d H:i:s').'] '.$msg;
	    $logHandle = fopen(WP_CONTENT_DIR . '/debug.log', 'a');
	    fwrite($logHandle, $msg."\n\n");
    }
    else {
		// no op
	}
   
  } // end mcmLog()


  /**
   * Shortcode callback - called on add_shortcode action - will display users table if logged-in user is an administrator
   *       
   * @param string $atts not used
   * 
   * @return string content to be displayed in place of shortcode 
   */ 
  function shortCode($atts) {  	 
	  
	  if ( !$this->checkForAdminAccess() )
	    return __('You do not have access to this content', 'mcm-user-list');
	  
	  $role = ( isset($_GET['role']) ) ? $this->validateRole($_GET['role']) : '';
	  $pageNum = ( isset($_GET['pageNum']) ) ? intval($_GET['pageNum']) : 0;
	  
	  if ( isset($_GET['orderByField']) )
	    $this->validateOrderByField($_GET['orderByField']);
	  $orderByField = ( isset($_GET['orderByField']) ) ? $_GET['orderByField'] : '';
	  
	  $sortOrder = ( isset($_GET['sortOrder']) ) ? $this->validateSortOrder($_GET['sortOrder']) : '';
	  
	  $ret = $this->listUsers($role, $pageNum, $orderByField, $sortOrder);	  
	  
	  return $ret;
      
  } // end filter()  
  
  
  /**
   * Check for admin access 
   *       
   * @param void None
   * 
   * @return boolean true if administrator logged-in, false otherwise
   */ 
  private function checkForAdminAccess() { 
  
    return current_user_can('administrator');
   	  
  } // end checkForAdminAccess()	  
  
  
  /** 
   * sanitize/only allow proper values for $orderByField
   *        
   * @param string $orderByField field to order by
   * 
   * @return void
   */ 
  private function validateOrderByField($orderByField) {
	  
    $allowedOrderByFields = array('', 'display_name', 'user_login', 'role');
    if ( !in_array($orderByField, $allowedOrderByFields) )
      die(' invalid order by');	  
	  
  } // end validateOrderByField()
  
  
  /** 
   * validate sort order
   *         
   * @param string $sortOrder order to sort by, defaults to ASC
   * 
   * @return string filtered sort order 
   */ 
  private function validateSortOrder($sortOrder) {

    if ($sortOrder == 'DESC')
      $sortOrder = 'DESC';
      
    return $sortOrder;  
	  
  } // end validateSortOrder()
  
  
  /** 
   * Called on AJAX to create order by pagination links
   *           
   * @param void None
   * 
   * @return void 
   */ 
  function mcm_user_list_ajax_pagination() {
	  
	// only allow logged-in adminstrators to use this  
  if ( !current_user_can('administrator') )
	  die(' No access');
	  
	// check nonce passed in
	check_ajax_referer(self::MCM_USER_LIST_ROLE_NONCE);   
	
	$role = ( isset($_GET['role']) ) ? $this->validateRole($_GET['role']) : '';
	
	$orderByField = '';
	if ( isset($_GET['orderByField']) ) { 
	  $this->validateOrderByField($_GET['orderByField']);
	  $orderByField = $_GET['orderByField'];
	}
	
	$sortOrder = ( isset($_GET['sortOrder']) ) ? $this->validateSortOrder($_GET['sortOrder']) : '';  
	
	$page = intval($_GET['page']);
	
	$this->mcmAJAXHelper($role, $orderByField, $sortOrder, $page);	  
	  
  } // end mcm_user_list_ajax_pagination()	  
  
  
  /** 
   * Called on AJAX to order by clicked field
   *            
   * @param void None
   * 
   * @return void 
   */ 
  function mcm_user_list_ajax_order_by_field() {
	
	// only allow logged-in adminstrators to use this
	if ( !current_user_can('administrator') ) 
	  die(' No Access');
	  
	// check nonce passed in
	check_ajax_referer(self::MCM_USER_LIST_ROLE_NONCE);   
	
	$role = ( isset($_GET['role']) ) ? $this->validateRole($_GET['role']) : '';
	
	$orderByField = '';
	if ( isset($_GET['orderByField']) ) { 
	  $this->validateOrderByField($_GET['orderByField']);
	  $orderByField = $_GET['orderByField'];
	}
	
	$sortOrder = ( isset($_GET['sortOrder']) ) ? $this->validateSortOrder($_GET['sortOrder']) : '';  
	
	$this->mcmAJAXHelper($role, $orderByField, $sortOrder);
	  
  }	// end mcm_user_list_ajax_order_by_field()  
  
 
  /**
   * Code shared by multiple AJAX actions for retrieving and sorting users
   *            
   * @param string $role
   * @param string $orderByField the field to order by
   * @param string $sortOrder the direction to sort by (ASC or DESC)
   * @param int $page the page number in pagination, defaults to 1
   * 
   * @return void 
   */ 
  private function mcmAJAXHelper($role, $orderByField, $sortOrder, $page=1) {
	  
    $usersData = $this->getUsers($role, $page, $orderByField, $sortOrder);
    $users = $usersData['users'];
    $numResults = $usersData['count'];  
    
    $usersArr = array();
    if ( count($users) ) {
	  foreach ($users as $user) {	
		$userRolesArr = $user->roles; // Get all the user roles for earch user as an array
		$userRolesStr = '';
        if ( count($userRolesArr) ) 
		  $userRolesStr = implode(', ', $userRolesArr); 			
        
        $usersArr[] = array(
                        'role' => esc_html(ucwords($userRolesStr)),
                        'display_name' => esc_html($user->display_name),
				        'user_login' => esc_html($user->user_login)
				     );
      }
	}	
	
	$ret = array( 
	         'users' => $usersArr,
             'num_results' => $numResults
           );
           
    // return users as JSON
    echo json_encode($ret);
    
    wp_die(); // All ajax handlers die when finished	  
	  
  } // end mcmAJAXHelper();	  
  
  
  /** 
   * Called on AJAX to filter by role
   *  
   * @param void None
   * 
   * @return void 
   */ 
  function mcm_user_list_ajax_role_filter() {
    	  
    // only allow logged-in adminstrators to use this	  
	if ( !current_user_can('administrator') ) 
	  die(' No Access');
	  
	// check nonce passed in
	check_ajax_referer(self::MCM_USER_LIST_ROLE_NONCE);  
	  
	$role = ( isset($_GET['role']) ) ? $this->validateRole($_GET['role']) : '';
	
	// just use default order by field and sort order  
	$orderByField = '';	  
	$sortOrder = '';  
	
	$this->mcmAJAXHelper($role, $orderByField, $sortOrder);
	  
  } // end mcm_user_list_ajax_role_filter()
  	  
  
  /** 
   * Retrieve the users with role filter, pagination, and sort order
   *  
   * @param string $role
   * @param int $pageNum the page number in pagination, defaults to 1
   * @param string $orderByField the field to order by
   * @param string $sortOrder the direction to sort by (ASC or DESC)
   * 
   * @return array users retreived 
   */
  private function getUsers($role, $pageNum, $orderByField, $sortOrder) {

    $this->validateOrderByField($orderByField); 
    
    $sortOrder = $this->validateSortOrder($sortOrder);

    $args = array(
      'orderby'      => $orderByField,
	  'number'       => self::MCM_NUM_USERS_PER_PAGE /* Limit the total number of users returned */
    );
    
    if ( strlen($sortOrder) )
      $args['order'] = $sortOrder;
    
    if ($pageNum) {
      $args['paged'] = intval($pageNum); // Determines which page should be returned when used with number.
      $args['offset'] = ($args['paged']-1)*self::MCM_NUM_USERS_PER_PAGE;
    } 
    
    if ($role != '')
      $args['role'] = $this->validateRole($role);  
    	
	$userQuery = new WP_User_Query($args);	
	$usersArr = array( $userQuery->get_results() ); 
    $users = $usersArr[0];
  
    $count = $userQuery->get_total();
    
    $ret = array(
      'users' => $users,
      'count' => $count 
    );
  
    return $ret;
	  
  } // end getUsers	  
  
  
  /**
   * Gets options for HTML SELECT for role
   * 
   * @param string $role
   * 
   * @return string roles in HTML OPTION tags 
   */
  private function getRoleOptions($role) {
	  
    $allRoles = $this->getAllRoles();
    $allRolesOptions = '';
    if ( count($allRoles) ) {
	  foreach ($allRoles as $key=>$value) {
	    $allRolesOptions .= '<option value="'.esc_html($key).'"'.( ($key==$role) ? ' selected="selected"' : '' ).'>'.esc_html($value).'</option>'."\n";	  
	  }	  	
	}		  
	  
	return $allRolesOptions;  
	  
  } // end getRoleOptions()
    	  

  /**
   * Display the users in the HTML table
   * 
   * @param string $role
   * @param int $pageNum the page number in pagination, defaults to 1
   * @param string $orderByField the field to order by
   * @param string $sortOrder the direction to sort by (ASC or DESC)
   * 
   * @return string HTML for users table
   */
  private function listUsers($role, $pageNum, $orderByField, $sortOrder) {
	  
	if ( !current_user_can('administrator') ) // CHECK FOR ADMIN ACCESS
      die(' No Access');

    $usersData = $this->getUsers($role, $pageNum, $orderByField, $sortOrder);
    $users = $usersData['users'];
    $numResults = $usersData['count'];
	
	$filtersForSort = '';    
	if ( strlen($role) )
	  $filtersForSort .= '&amp;role='.$this->validateRole($role);   
	
	$displayNameSortBy = ( ($orderByField == 'display_name') && ($sortOrder != 'DESC') ) ? 'DESC' : '';
    $usernameSortBy = ( ($orderByField == 'user_login') &&  ($sortOrder != 'DESC') ) ? 'DESC' : '';
		     
	$allRolesOptions = $this->getRoleOptions($role);	
	
	$roleNonce = wp_create_nonce( self::MCM_USER_LIST_ROLE_NONCE );       
	    
    $html ='<div class="mcm-user-list-wrapper">'."\n".
             '<form class="mcm-filter" action="" method="GET" id="mcm-filter-form">'."\n".
		      '<select name="role" class="mcm-role" id="mcm-role" onchange="roleFormOnsubmitHandler()">'."\n".
		        '<option value="">Select Role..</option>'."\n".
		        $allRolesOptions.
		      '</select>'."\n".
		      '<input type="hidden" name="num_users_per_page" id="num_user_per_page" value="'.self::MCM_NUM_USERS_PER_PAGE.'" />'."\n".
		      '<input type="hidden" name="prevOrderBy" id="prevOrderBy" value="" />'."\n".
		      '<input type="hidden" name="currentPageNumber" id="currentPageNumber" value="1" />'."\n".
		    '</form>'."\n".
		    '<table class="mcm-user-list" id="mcm-user-list">'."\n".
		    '<thead>'."\n".
		    '<tr>'."\n".
		      '<th><a href="#" onclick="mcmOrderByFieldOnclickHandler(\'user_login\')"><div class="mcm-sort-by-desc" id="mcm-sort-by-desc-user-name" style="display: none"></div>'.__('Username', 'mcm-user-list').'</a></th>'."\n".
		      '<th><div class="mcm-sort-by-desc" id="mcm-sort-by-desc-role" style="display: none;"></div>'.__('Role', 'mcm-user-list').'</th>'."\n".
		      '<th><a href="#" onclick="mcmOrderByFieldOnclickHandler(\'display_name\')"><div class="mcm-sort-by-desc" id="mcm-sort-by-desc-display-name" style="display: none"></div>'.__('Display Name', 'mcm-user-list').'</a></th>'."\n".
		      	    '</tr>'."\n".
		    '</thead>'."\n".
		    '<tbody id="mcm-user-list-table-body">'."\n";
		    
    if ( count($users) ) {
	  foreach ($users as $user) {		  
		$userRolesArr = $user->roles; // Get all the user roles for earch user as an array
		$userRolesStr = '';
        if ( count($userRolesArr) ) 
		  $userRolesStr = implode(', ', $userRolesArr); 			
        		
        $html .= '<tr>'."\n".                      
				      '<td>'. esc_html($user->user_login).'</td>'."\n".
				      '<td>'. esc_html(ucwords($userRolesStr)).'</td>'."\n".
				      '<td>'. esc_html($user->display_name).'</td>'."\n".
				    '</tr>'."\n";  	  
	  }	  
	  reset($users);	
	}	
	else {
      $html .= '<tr><td colspan="3" class="mcm-error">'.__('Sorry, no users were found matching those criteria', 'mcm-user-list').'</td></tr></tbody>'."\n";		
	}	  
    $html .= '</table>'."\n";
    $html .= $this->generatePagination($numResults, $role);    
    $html .= '</div>'; // close <div class="mcm-user-list-wrapper">
    
    return $html;
	  
  }	// end listUsers()   
  
  
  /**
   * Generate pagination links HTML
   * 
   * @param int $numResults the number of users found
   * @param string $role
   * 
   * @return string HTML for pagination links
   */
  private function generatePagination($numResults, $role) {
	  
    $html = '<div class="mcm-pagination">';
	$numPages = ceil( $numResults/((float)self::MCM_NUM_USERS_PER_PAGE) );
	
	if ( $numPages > 1 ) {
	  $html .= '<ul class="mcm-users-pagination">'."\n";
	    	    
	  if ( isset($_GET['pageNum']) && ($_GET['pageNum'] > 1) ) 
	    $html .= '<li><a  href="#" onclick="mcmPaginationOnclick('.intval($_GET['pageNum']-1).', \'\', \'\', \'\')">Previous</a></li>'."\n"; // previous page link   
	    	    
	  if ( !isset($_GET['pageNum']) )
	    $_GET['pageNum'] = 1;
	    
	  for ($p = 1; $p <= $numPages; $p++) { // generate pagination links (1..$numPages)
	    if ($p != $_GET['pageNum'])      
	      $html .= '<li><a href="#" onclick="mcmPaginationOnclick('.$p.', \'\', \'\', \'\')">'.$p.'</a></li>'."\n";
	    else 	     
	      $html.= '<li>'.$p.'</li>'."\n"; // already on this page, no need to link to it	     
	  } // end for
	  
	  if ( $numResults > ( $_GET['pageNum'] * self::MCM_NUM_USERS_PER_PAGE ) ) 	      
	      $html .= '<li><a href="#" onclick="mcmPaginationOnclick('.intval($_GET['pageNum']+1).', \'\', \'\', \'\')">Next</a></li>'."\n"; // next page link here 	  
	  
	  $html .= '</ul>'."\n";
	    
    } // end if ( $numPages > 1 )
    $html .= ' <div class="mcm-num-found"><span id="mcm-num-results">'.$numResults.'</span> '.__('Users found', 'mcm-user-list').'</div>'."\n";
    $html .= '</div>'."\n";	 // close <div class="mcm-pagination"> 
	
	return $html;
	  
  } // end generatePagination() 

}
?>
