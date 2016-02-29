<?php
/**
 * @package bootstrapped_login
 * @version 0.3
 */
/*
Plugin Name: Bootstrapped login
Plugin URI: https://github.com/humanific/bootstrapped-login
Description: Shortcode for displaying bootstrap login
Author: Francois Richir
Version: 0.3
Author URI: http://humanific.com
*/



function bootstrapped_ajax_login(){
    check_ajax_referer( 'bootstrapped_login_nonce', 'nonce' );
    $info = array();
    $info['user_login'] = $_POST['username'];
    $info['user_password'] = $_POST['password'];
    $info['remember'] = true;

    $user_signon = wp_signon( $info, false );
    if ( is_wp_error($user_signon) ){
        echo json_encode(array('loggedin'=>false, 'message'=>'error'));
    } else {
		wp_set_current_user($user_signon->ID);
        wp_set_current_user( $user_signon->ID);
        wp_set_auth_cookie( $user_signon->ID );
        echo json_encode(array('loggedin'=>true, 'message'=>'success'));
    }
  die();
}

function bootstrapped_ajax_checkusername(){
    die( username_exists($_GET['username']) ? json_encode(false) : json_encode(true) ) ;
}

function bootstrapped_ajax_checkemail(){
    die( email_exists($_GET['email']) ? json_encode(false) : json_encode(true) ) ;
}


function bootstrapped_ajax_register(){
    check_ajax_referer( 'ajax-signup-nonce', 'security-signup' );

    $userdata = array(
        'user_pass'    =>  $_POST['password'],
        'user_email'  => $_POST['email'],
        'user_login'    =>  $_POST['username']
    );
    $user_id = wp_insert_user( $userdata ) ;
    if( !is_wp_error($user_id) ) {
      wp_set_current_user( $user_id);
      wp_set_auth_cookie( $user_id );
      echo json_encode(array('register'=>true, 'message'=>'success','info'=>__('Signup successful','bootstrapped-login')));
    } else{
      $errors = array(
        'existing_user_login'=> __('This username is taken','bootstrapped-login'),
        'existing_user_email'=>__('This email is already registered','bootstrapped-login')
        );
      echo json_encode(array('register'=>false, 'message'=>'error', 'info'=>$errors[$user_id->get_error_code()]));
    }
    die();
}



function bootstrapped_ajax_lostpassword(){
  global $wpdb;
  check_ajax_referer( 'ajax-pwd-nonce', 'security-pwd' );
  if(empty($_POST['email'])) {
    die("<div class='alert alert-danger'>".__('Please enter your Username or E-mail address','bootstrapped-login')."</div>");
  }
  $user_input = trim($_POST['email']);
  if ( strpos($user_input, '@') ) {
    $user_data = get_user_by_email($user_input);
    if(empty($user_data) || $user_data->caps[administrator] == 1) { //delete the condition $user_data->caps[administrator] == 1, if you want to allow password reset for admins also
       die("<div class='alert alert-danger'>".__('Invalid E-mail address','bootstrapped-login')."</div>");
    }
  }
  else {
    $user_data = get_userdatabylogin($user_input);
    if(empty($user_data) || $user_data->caps[administrator] == 1) { //delete the condition $user_data->caps[administrator] == 1, if you want to allow password reset for admins also
       die("<div class='alert alert-danger'>".__('Invalid Username','bootstrapped-login')."</div>");
    }
  }
  $user_login = $user_data->user_login;
  $user_email = $user_data->user_email;

  $key = $wpdb->get_var($wpdb->prepare("SELECT user_activation_key FROM $wpdb->users WHERE user_login = %s", $user_login));
  if(empty($key)) {
    $key = wp_generate_password(20, false);
    $wpdb->update($wpdb->users, array('user_activation_key' => $key), array('user_login' => $user_login));
  }

  $message = __('Someone requested that the password be reset for the following account:') . "\r\n\r\n";
  $message .= home_url( ) . "\r\n\r\n";
  $message .= sprintf(__('Username: %s'), $user_login) . "\r\n\r\n";
  $message .= __('If this was a mistake, just ignore this email and nothing will happen.') . "\r\n\r\n";
  $message .= __('To reset your password, visit the following address:') . "\r\n\r\n";
  if($_POST['lang']){
    $langrequest = '&lang='.$_POST['lang'];
  }
  $message .=  get_option('siteurl'). "?resetpassword&key=" . rawurlencode($key) . "&login=" . $user_login .  $langrequest ."\r\n\r\n";
  $message = apply_filters('bootstrapped_login_reset_pwd_email_message', $message);
  do_action('bootstrapped_login_reset_pwd_email', array('message' => $message,'email'=> $user_email));
  
  if ( $message && !wp_mail($user_email, __('Password Reset Request','bootstrapped-login'), $message, array('Content-Type: text/plain; charset=UTF-8')) ) {
     die("<div class='alert alert-danger'>".__('Email failed to send for some unknown reason.','bootstrapped-login')."</div>");
  } else {
     die("<div class='alert alert-success'>".__('We have just sent you an email with Password reset instructions.','bootstrapped-login')."</div>");
  }
}

add_action('after_setup_theme', 'bootstrapped_login_remove_admin_bar');

function bootstrapped_login_remove_admin_bar() {
  if (!current_user_can('administrator') && !is_admin()) {
    show_admin_bar(false);
  }
}

function bootstrapped_login_init(){
  if(!is_user_logged_in()){
    add_action( 'wp_ajax_nopriv_ajaxlogin', 'bootstrapped_ajax_login' );
    add_action( 'wp_ajax_nopriv_ajaxregister', 'bootstrapped_ajax_register' );
    add_action( 'wp_ajax_nopriv_ajaxlostpassword', 'bootstrapped_ajax_lostpassword' );
    add_action( 'wp_ajax_nopriv_ajax_checkusername', 'bootstrapped_ajax_checkusername' );
    add_action( 'wp_ajax_nopriv_ajax_checkemail', 'bootstrapped_ajax_checkemail' );
    add_action('wp_footer', 'bootstrapped_login_modal', 100);
  }
}
add_action('init', 'bootstrapped_login_init');



function bootstrapped_login_modal(){
 if(file_exists( trailingslashit( get_stylesheet_directory() ) . 'bootstraped-login-template.php')){
    include trailingslashit( get_stylesheet_directory() ) . 'bootstraped-login-template.php';
  } else{
    include 'bootstraped-login-template.php';
  }
}


function bootstrapped_save_profile($user_id){
    $usermetas = array(
        'first_name'    =>  $_POST['first_name'],
        'last_name'    =>  $_POST['last_name'],
        'city'    =>  $_POST['city'],
        'country'    =>  $_POST['country'],
        'postalcode'    =>  $_POST['postalcode'],
        'address'    =>  $_POST['address'],
        'newsletter'    =>  $_POST['newsletter'],
        'children'    =>  $_POST['children'],
        'usertitle'    =>  $_POST['usertitle']
    );
    if($_POST['date_year']>'1900'){
      $usermetas['dob']    =  $_POST['date_month'].'/'.$_POST['date_day'].'/'.$_POST['date_year'];
    }
    foreach ($usermetas as $key => $value) {
      update_user_meta( $user_id, $key, $value );
    }
}


function bootstrapped_get_user_profile($user_id){
  $usermetas = array('first_name' ,'last_name','city','country','postalcode','address','newsletter', 'children','usertitle','dob');
  $profile = array();
  foreach ($usermetas as $key) {
    $profile[$key] = get_user_meta( $user_id, $key , true );
  }
  return $profile;
}




function bootstrapped_reset_password(){
    global $wpdb, $user_ID;
    $reset_key = $_GET['key'];
    $user_login = $_GET['login'];
    $user_data = $wpdb->get_row($wpdb->prepare("SELECT ID, user_login, user_email FROM $wpdb->users WHERE user_activation_key = %s AND user_login = %s", $reset_key, $user_login));

    $user_login = $user_data->user_login;
    $user_email = $user_data->user_email;

    if(!empty($reset_key) && !empty($user_data)) {
      $new_password = wp_generate_password(7, false);
        //echo $new_password; exit();
        wp_set_password( $new_password, $user_data->ID );
        //mailing reset details to the user
      $message = __('Your new password for the account at:') . "\r\n\r\n";
      $message .= get_option('siteurl') . "\r\n\r\n";
      $message .= sprintf(__('Username: %s'), $user_login) . "\r\n\r\n";
      $message .= sprintf(__('Password: %s'), $new_password) . "\r\n\r\n";
      $message .= __('You can now login with your new password at: ') . get_option('siteurl')."/login" . "\r\n\r\n";

      if ( $message && !wp_mail($user_email,__('Password Reset Request','bootstrapped-login') , $message, array('Content-Type: text/plain; charset=UTF-8')) ) {
        return "<div class='alert alert-danger'>".__('Email failed to send for some unknown reason.','bootstrapped-login')."</div>";
      }
      else {
        return "<div class='alert alert-success'>".__('A new password has been sent to your email address','bootstrapped-login')."</div>";
      }
    }
    else exit('Not a Valid Key.');
}



function bootstrapped_reset_pwd_form (){
    global $wpdb, $user_ID;
    $reset_key = $_GET['key'];
    $user_login = $_GET['login'];
    $user_data = $wpdb->get_row($wpdb->prepare("SELECT ID, user_login, user_email FROM $wpdb->users WHERE user_activation_key = %s AND user_login = %s", $reset_key, $user_login));

    $user_login = $user_data->user_login;
    $user_email = $user_data->user_email;

    if(!empty($reset_key) && !empty($user_data)) {
      if($_POST['password'] && strlen($_POST['password'])>=4) {
          wp_set_password( $_POST['password'] , $user_data->ID );
          ?>
          <div class="alert alert-success"><?php _e('Your password has been updated','bootstrapped-login'); ?></div>
          <?php
      } else{
    ?>
        <h4><?php _e('Password Reset Request','bootstrapped-login'); ?></h4>
        <form class="form-horizontal"  role="form" id="pwdform" method="POST" action="">
        <div class="form-group">
        <label class="col-sm-6 control-label" for="password"><?php _e('Password','bootstrapped-login'); ?></label>
        <div class="col-sm-6">
        <input type="password" name="password" id="password" class="form-control required" />
        </div>
        </div>
        <div class="form-group">
        <label class="col-sm-6 control-label" for="password2"><?php _e('Repeat password','bootstrapped-login'); ?></label>
        <div class="col-sm-6">
        <input type="password" name="password2" id="password2" class="form-control required" />
        </div>
        </div>
          <div class="form-group">
              <div class="col-sm-offset-6 col-sm-6">
                <input type="submit" name="submit" class="btn btn-primary" value="<?php _e('Update password','bootstrapped-login'); ?>"/>
              </div>
            </div>
          </form>
    <script>

      jQuery(document).ready(function($){
         $( "#pwdform" ).validate({
           errorClass: "text-danger small",
           highlight: function(element) {
               $(element).closest('.form-group').addClass('has-error');
           },
           unhighlight: function(element) {
               $(element).closest('.form-group').removeClass('has-error');
           },
           errorPlacement: function(error, element) {
               element.closest('div').append(error);
           },


            rules: {
              password: {
                required: true,
                minlength: 5
              },
              password2: {
                equalTo: "#pwdform #password"
              }
            }
          });

      })
      jQuery.extend(jQuery.validator.messages, {
            required: "<?php _e("This field is mandatory",'bootstrapped-login'); ?>",
            email: "<?php _e('Please check this email address','bootstrapped-login'); ?>",
            equalTo:"<?php _e('Please enter the same value again.','bootstrapped-login');?>",
            minlength: jQuery.format("<?php _e('Enter at least {0} characters','bootstrapped-login');?>")

       });
    </script>

  <?php    }

    }
    else _e('Not a Valid Key.','bootstrapped-login');
}






add_filter('template_include', 'bootstrapped_reset_password_template', 1, 1);
function bootstrapped_reset_password_template($template){
    if(isset($_GET['resetpassword'])){
        return dirname(__FILE__) . '/bootstrapped_login_resetpassword.php';
    }
    return $template;
}



function bootstrapped_change_pwd_form (){
    $user = wp_get_current_user();
    if($user){
      if($_POST['password'] && strlen($_POST['password'])>4 ) {
        if(wp_check_password( $_POST['oldpassword'], $user->data->user_pass, $user->ID)){
          wp_set_password( $_POST['password'] , $user->ID );
          ?>
          <div class="alert alert-success"><?php _e('Your password has been changed','bootstrapped-login'); ?></div>
          <?php
        }else{
          ?>
          <div class="alert alert-danger"><?php _e('Wrong password','bootstrapped-login'); ?></div>
          <?php
          $passerror = true;
        }
      }

      if($passerror || !isset($_POST['password'])){
    ?>
    <h1></h1>
        <form class="form-horizontal"  role="form" id="pwdform" method="POST" action="?savepass">
        <div class="form-group">
        <label class="col-sm-6 control-label" for="oldpassword"><?php _e('Old password','bootstrapped-login'); ?></label>
        <div class="col-sm-6">
        <input type="oldpassword" name="oldpassword" id="oldpassword" class="form-control required" />
        </div>
        </div>
        <div class="form-group">
        <label class="col-sm-6 control-label" for="password"><?php _e('Password','bootstrapped-login'); ?></label>
        <div class="col-sm-6">
        <input type="password" name="password" id="password" class="form-control required" />
        </div>
        </div>
        <div class="form-group">
        <label class="col-sm-6 control-label" for="password2"><?php _e('Repeat password','bootstrapped-login'); ?></label>
        <div class="col-sm-6">
        <input type="password" name="password2" id="password2" class="form-control required" />
        </div>
        </div>
          <div class="form-group">
              <div class="col-sm-offset-6 col-sm-6">
                <input type="submit" name="submit" class="btn btn-primary" value="<?php _e('Update password','bootstrapped-login'); ?>"/>
              </div>
            </div>
          </form>
    <script>

      jQuery(document).ready(function($){
         $( "#pwdform" ).validate({
            rules: {
              oldpass:{required: true},
              password: {
                required: true,
                minlength: 5
              },
              password2: {
                equalTo: "#pwdform #password"
              }
            }
          });

      })
      jQuery.extend(jQuery.validator.messages, {
            required: "<?php _e("This field is mandatory",'bootstrapped-login'); ?>",
            email: "<?php _e('Please check this email address','bootstrapped-login'); ?>",
            equalTo:"<?php _e('Please enter the same value again.','bootstrapped-login');?>",
            minlength: jQuery.format("<?php _e('Enter at least {0} characters','bootstrapped-login');?>")

       });
    </script>

  <?php    } }
}


function bootstrapped_change_passwordform_shortcode( $atts, $content = null ) {
   ob_start();
   bootstrapped_change_pwd_form();
  return ob_get_clean();
}

add_shortcode( 'changepasswordform', 'bootstrapped_change_passwordform_shortcode' );



function bootstrapped_passwordform_shortcode( $atts, $content = null ) {
  ob_start();
  bootstrapped_reset_pwd_form();
  return ob_get_clean();
}

add_shortcode( 'passwordform', 'bootstrapped_passwordform_shortcode' );



function bootstrapped_usermenu_shortcode( $atts, $content = null ) {
  $tag = isset($atts['tag']) && $atts['tag']=="li" ? 'li' : 'div';
   ob_start();?>
      <?php if ( is_user_logged_in() ):
        global $current_user;
        get_currentuserinfo();
        $fb = get_user_meta( $current_user->ID, 'fbid', true );

        ?>
        <<?php echo $tag;?> class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown">
        <?php if($fb) echo '<img src="https://graph.facebook.com/'.$fb.'/picture" width="15" height="15" alt="icon" class="fbimage"/> ';?> <?php echo __('Hi','bootstrapped-login').' '.$current_user->display_name;?>
        <span class="caret"></span></a>
        <ul class="dropdown-menu" role="menu" >
        <li><a href="<?php echo wp_logout_url( bootstrapped_login_full_path() );?>"><i class="glyphicon glyphicon-log-out"></i> <?php _e('Sign out','bootstrapped-login');?></a></li>
        </ul>
        </<?php echo $tag;?>>
      <?php else:?>
        <<?php echo $tag;?> class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown"> <i class="glyphicon glyphicon-log-in "></i> <?php _e('Sign in','bootstrapped-login');?> <span class="caret"></span></a>
        <ul class="dropdown-menu" role="menu" >
        <li><a href="#" class="ajaxlogin"><?php _e('Log in','bootstrapped-login');?></a></li>
        <li><a href="#" class="ajaxsignup"><?php _e('Register','bootstrapped-login');?></a></li>
        </ul>
        </<?php echo $tag;?>>
      <?php endif;?>
   <?php
  return ob_get_clean();
}

add_shortcode( 'usermenu', 'bootstrapped_usermenu_shortcode' );

add_filter('widget_text', 'do_shortcode');


function bootstrapped_check_user_role( $role, $user_id = null ) {
  if ( is_numeric( $user_id ) )
    $user = get_userdata( $user_id );
  else
    $user = wp_get_current_user();
  if ( empty( $user ) )
    return false;
  if (in_array( strtolower($role) , $user->roles )) return true;
  return false;
}


function bootstrapped_useraccess_shortcode( $atts, $content = null ) {
  if(is_user_logged_in()){
    global $current_user;
    $data = array('user_nicename','user_email','user_login');
    get_currentuserinfo();
    foreach ($data as $key) {
      $content = str_replace('{{'.$key.'}}',$current_user->data->{$key}, $content);
    }
    $content = str_replace('{{logouturl}}', wp_logout_url( bootstrapped_login_full_path() ) , $content);
  }

  $content = do_shortcode($content);


  if(!isset($atts['grant']) || $atts['grant']=="subscriber" ){
    return is_user_logged_in() ? $content : "" ;
  } elseif ($atts['grant']=="loggedoff" && !is_user_logged_in()) {
    return $content ;
  } elseif (bootstrapped_check_user_role($atts['grant'])) {
    return $content;
  }
}


add_shortcode( 'useraccess', 'bootstrapped_useraccess_shortcode' );


function bootstrapped_forget_password_form(){
      if($_REQUEST['action'] == "tg_pwd_reset"){
        $msg = bootstrapped_ask_password_reset();
        die($msg);
      }
      if(isset($_GET['key']) && $_GET['action'] == "reset_pwd") {
        echo bootstrapped_reset_password();
      } else { ?>
    <?php }
  }



function bootstrapped_login_signup_vars(){

  $vars = array( 'validate_required' =>__("This field is required",'bootstrapped-login'),
      'validate_email' =>__('Please enter a valid email address','bootstrapped-login'),
      'validate_equalTo' =>__('Please enter the same value again.','bootstrapped-login'),
      'validate_minlength' =>__('Enter at least {0} characters','bootstrapped-login'),
      'validate_alphanumeric' =>__('Enter at least {0} characters','bootstrapped-login'),

      'checking_email' =>__('Checking email address','bootstrapped-login'),
      'sending_info' =>__('Sending user info, please wait...','bootstrapped-login'),
      'login_successfull' => __('Login successful, redirecting...','bootstrapped-login'),
      'login_error' =>__('Wrong username or password.','bootstrapped-login'),
      'validate_username' => __('This username is already taken.','bootstrapped-login'),
      'ajaxurl' => admin_url( 'admin-ajax.php'),
      'login_nonce' => wp_create_nonce( "bootstrapped_login_nonce" )
      );

  if(function_exists('pll_current_language')){
    $vars['lang'] = pll_current_language();
  }
  return $vars;
}

function bootstrapped_login_scripts() {
  wp_enqueue_script( 'jquery.validate', plugins_url( 'js/jquery.validate.min.js' , __FILE__ ), array( 'jquery') );
  wp_register_script( 'ajaxlogin', plugins_url( 'bootstraped-login.js' , __FILE__ ), array( 'jquery','jquery.validate','bootstrap' )  );
  wp_enqueue_script( 'jquery.validate.additional', plugins_url( 'js/additional-methods.min.js' , __FILE__ ), array( 'jquery.validate' )  );
  wp_localize_script('ajaxlogin', 'ajaxlogin', bootstrapped_login_signup_vars());
  wp_enqueue_script( 'ajaxlogin');
}


add_action( 'wp_enqueue_scripts', 'bootstrapped_login_scripts' );

function bootstrapped_login_styles() {
  wp_enqueue_style( 'bootstraped-login', plugins_url( 'bootstraped-login.css' , __FILE__ ));
}

add_action( 'wp_print_styles', 'bootstrapped_login_styles', 100 );


function bootstrapped_login_full_path(){
    $s = &$_SERVER;
    $ssl = (!empty($s['HTTPS']) && $s['HTTPS'] == 'on') ? true:false;
    $sp = strtolower($s['SERVER_PROTOCOL']);
    $protocol = substr($sp, 0, strpos($sp, '/')) . (($ssl) ? 's' : '');
    $port = $s['SERVER_PORT'];
    $port = ((!$ssl && $port=='80') || ($ssl && $port=='443')) ? '' : ':'.$port;
    $host = isset($s['HTTP_X_FORWARDED_HOST']) ? $s['HTTP_X_FORWARDED_HOST'] : (isset($s['HTTP_HOST']) ? $s['HTTP_HOST'] : null);
    $host = isset($host) ? $host : $s['SERVER_NAME'] . $port;
    $uri = $protocol . '://' . $host . $s['REQUEST_URI'];
    $segments = explode('?', $uri, 2);
    $url = $segments[0];
    return $url;
}



function bootstrapped_login_load_textdomain() {
  load_plugin_textdomain('bootstrapped-login', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');
}
add_action('init', 'bootstrapped_login_load_textdomain');


add_action('admin_menu', 'bootstrapped_login_menu_init');

function bootstrapped_login_menu_init(){
   add_options_page( 'Bootstrapped Login settinge', 'Bootstrapped Login', 'manage_options', 'bootstrapped_login_menu_settings', 'bootstrapped_login_menu_settings');
}

function bootstrapped_login_menu_settings(){
?>
<div class="wrap">
<div id="icon-tools" class="icon32"><br></div><?php echo "<h2>".__('Bootstrapped Login settings')."</h2>";?>
<form  method="post">
<?php
  if($_POST) {
	update_option( 'bootstrapped-email-confirmation', $_POST['emailconfirmation'] );
	echo "settings saved";
  }
?>
<table class="form-table">
<tr>
<th scope="row"><label for="emailconfirmation">Email confirmation</label></th>
<td><input name="emailconfirmation" type="checkbox" id="emailconfirmation" value="1" <?php echo get_option('bootstrapped-email-confirmation') ? ' checked ':'' ?> /></td>
</tr>
</table>
<p class="submit">
<input class="button button-primary" type="submit" name="Submit" value="<?php _e('Save Changes' ) ?>" />
</p>
</form>
</div> <!-- end wrap -->
<?php
}

?>
