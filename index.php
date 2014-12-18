<?php
/**
 * @package bootstrapped_login
 * @version 0.1
 */
/*
Plugin Name: Bootstrapped login
Plugin URI: https://github.com/humanific/bootstrapped-login
Description: Shortcode for displaying bootstrap login
Author: Francois Richir
Version: 0.1
Author URI: http://humanific.com
*/


require_once('facebook/facebook.php');


function bootstrapped_ajax_login(){
    check_ajax_referer( 'ajax-login-nonce', 'security' );
    $info = array();
    $info['user_login'] = $_POST['username'];
    $info['user_password'] = $_POST['password'];
    $info['remember'] = true;

    $user_signon = wp_signon( $info, false );
    if ( is_wp_error($user_signon) ){
        echo json_encode(array('loggedin'=>false, 'message'=>'error'));
    } else {
        echo json_encode(array('loggedin'=>true, 'message'=>'success'));
    }
  die();
}

function bootstrapped_ajax_checkusername(){
    die( username_exists($_GET['username']) ? json_encode(false) : json_encode(true) ) ;
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



function bootstrapped_login_fb_init(){

if( strpos($_SERVER['REQUEST_URI'] ,"/fblogin") !== false ){
    $facebook = new Facebook(array(
      'appId'  => get_option('fb_appid'),
      'secret' => get_option('fb_appsecret'),
      
    ));
    $fbuser = $facebook->getUser();
    if ($fbuser) {
      try {
        $user_profile = $facebook->api('/me');
      }
      catch (Exception $e) {
        echo $e->getMessage();
        exit();
      }
      $user_fbid  = $fbuser;
      $user_email = $user_profile["email"];
      $user_fnmae = $user_profile["first_name"];
      $user_profile['fbid'] = $fbuser;
      if( email_exists( $user_email )) { // user is a member 
        $user = get_user_by('email', $user_email );
        $user_id = $user->ID;
        if(!get_user_meta( $user_id, 'fbid', true )){
          update_user_meta( $user_id, 'fbdata' , json_encode($user_profile) );
          update_user_meta( $user_id, 'fbid' , $fbuser );
          if(!get_user_meta( $user_id, 'first_name', true ))  update_user_meta( $user_id, 'first_name' , $user_profile["first_name"] );
          if(!get_user_meta( $user_id, 'last_name', true ))  update_user_meta( $user_id, 'last_name' , $user_profile["last_name"] );
          if(!get_user_meta( $user_id, 'dob', true ) && $user_profile["birthday"])  {
            update_user_meta( $user_id, 'dob' , $user_profile["birthday"] );
          }
        }
         wp_set_current_user( $user_id);
         wp_set_auth_cookie( $user_id );
         wp_redirect( $_GET['r'] ? $_GET['r'] : site_url() );
       } else {
        set_transient( 'user_profile', $user_profile, HOUR_IN_SECONDS );
        wp_redirect( site_url().'/register?facebook' );

       }
    } else{
    $fbloginurl = $facebook->getLoginUrl(array(
        'redirect_uri' => site_url().'/fblogin',
        'scope' => 'email,user_birthday'));
        ?><h3>PHP request</h3>
    <pre><?php print_r($_REQUEST); ?></pre>
    <h3>PHP Session</h3>
    <pre><?php print_r($_SESSION); ?></pre>
    <a href="<?php echo $fbloginurl; ?>">try again</a>
    <?php
    } 
    die();
}

}



add_action('init', 'bootstrapped_login_fb_init');

function bootstrapped_ajax_lostpassword(){
  global $wpdb;
  check_ajax_referer( 'ajax-pwd-nonce', 'security-pwd' );
  if(empty($_POST['email'])) {
    die("<div class='alert alert-danger'>".__('Please enter your Username or E-mail address','bootstrapped-login')."</div>");
  }
  //We shall SQL escape the input
  $user_input = $wpdb->prepare(trim($_POST['email']));
  
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
      //generate reset key
      $key = wp_generate_password(20, false);
      $wpdb->update($wpdb->users, array('user_activation_key' => $key), array('user_login' => $user_login));  
    }
    
    //mailing reset details to the user
    $message = __('Someone requested that the password be reset for the following account:') . "\r\n\r\n";
    $message .= home_url( ) . "\r\n\r\n";
    $message .= sprintf(__('Username: %s'), $user_login) . "\r\n\r\n";
    $message .= __('If this was a mistake, just ignore this email and nothing will happen.') . "\r\n\r\n";
    $message .= __('To reset your password, visit the following address:') . "\r\n\r\n";
    $message .= (function_exists('pll_home_url') ? pll_home_url() : home_url( )). "/lostpassword?action=reset_pwd&key=$key&login=" . rawurlencode($user_login) . "\r\n";
    
    if ( $message && !wp_mail($user_email, 'Password Reset Request', $message) ) {
       die("<div class='alert alert-danger'>".__('Email failed to send for some unknown reason.','bootstrapped-login')."</div>");
    } else {
       die("<div class='alert alert-success'>".__('We have just sent you an email with Password reset instructions.','bootstrapped-login')."</div>");
    }

}


function bootstrapped_login_init(){
  if(!is_user_logged_in()){
    add_action( 'wp_ajax_nopriv_ajaxlogin', 'bootstrapped_ajax_login' );
    add_action( 'wp_ajax_nopriv_ajaxregister', 'bootstrapped_ajax_register' );
    add_action( 'wp_ajax_nopriv_ajaxlostpassword', 'bootstrapped_ajax_lostpassword' );
    add_action( 'wp_ajax_nopriv_ajax_fb_login', 'ajax_fb_login' );
    add_action( 'wp_ajax_nopriv_bootstrapped_ajax_checkusername', 'bootstrapped_ajax_checkusername' );
    add_action('wp_footer', 'bootstrapped_login_modal', 100);
  }
}
add_action('init', 'bootstrapped_login_init');



function bootstrapped_login_modal(){ 
 include 'bootstraped-login-template.php';
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
      
      if ( $message && !wp_mail($user_email, 'Password Reset Request', $message) ) {
        return "<div class='alert alert-danger'>".__('Email failed to send for some unknown reason.','bootstrapped-login')."</div>";
      }
      else {
        return "<div class='alert alert-success'>".__('A new password has been sent to your email address','bootstrapped-login')."</div>";
      }
    } 
    else exit('Not a Valid Key.');
}



function bootstrapped_pwd_form (){
    global $wpdb, $user_ID;
    $reset_key = $_GET['key'];
    $user_login = $_GET['login'];
    $user_data = $wpdb->get_row($wpdb->prepare("SELECT ID, user_login, user_email FROM $wpdb->users WHERE user_activation_key = %s AND user_login = %s", $reset_key, $user_login));
    
    $user_login = $user_data->user_login;
    $user_email = $user_data->user_email;
    
    if(!empty($reset_key) && !empty($user_data)) {
      if($_POST['password'] && strlen($_POST['password'])>4) {
          wp_set_password( $_POST['password'] , $user_data->ID );
          ?>
          <div class="alert alert-success"><?php _e('Your password has been updated','bootstrapped-login'); ?></div>
          <?php
      } else{
    ?>
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
            rules: {
              password: {
                required: true,
                minlength: 4
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
    else exit('Not a Valid Key.');
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
                minlength: 4
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


function bootstrapped_changebootstrapped_passwordform_shortcode( $atts, $content = null ) {
   ob_start();
   bootstrapped_change_pwd_form();
  return ob_get_clean();
}

add_shortcode( 'changepasswordform', 'bootstrapped_changebootstrapped_passwordform_shortcode' );



function bootstrapped_passwordform_shortcode( $atts, $content = null ) {
   ob_start();
   bootstrapped_pwd_form();
  return ob_get_clean();
}

add_shortcode( 'passwordform', 'bootstrapped_passwordform_shortcode' );








function usermenu_shortcodepasswordform_shortcode( $atts, $content = null ) {
   ob_start();?>

      <?php if ( is_user_logged_in() ):
        global $current_user;
        get_currentuserinfo();
        $fb = get_user_meta( $current_user->ID, 'fbid', true );
        if($fb) echo '<img src="https://graph.facebook.com/'.$fb.'/picture" width="15" height="15" alt="icon" class="fbimage"/> ';
        echo __('Hi','bootstrapped-login').' '.$current_user->display_name;?> &nbsp;
        <a class="navbar-link" href="<?php echo wp_logout_url( bootstrapped_login_full_path() ); ?>" title="<?php _e('Log out','bootstrapped-login');?>"><?php _e('Log out','bootstrapped-login');?></a>
      <?php else:?>
      <a href="#"  class="ajaxlogin navbar-link" ><?php _e('Log in','bootstrapped-login');?></a> &nbsp;
      <a href="#"  class="ajaxsignup navbar-link" ><?php _e('Register','bootstrapped-login');?></a>
      <?php endif;?>

   <?php
  return ob_get_clean();
}

add_shortcode( 'usermenu', 'usermenu_shortcodepasswordform_shortcode' );

add_filter('widget_text', 'do_shortcode');


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
  $facebook = new Facebook(array(
    'appId'  => get_option('fb_appid'),
    'secret' => get_option('fb_appsecret')
  ));

  return array( 'validate_required' =>__("This field is mandatory",'bootstrapped-login'),
      'validate_email' =>__('Please check this email address','bootstrapped-login'),
      'validate_equalTo' =>__('Please enter the same value again.','bootstrapped-login'),
      'validate_minlength' =>__('Enter at least {0} characters','bootstrapped-login'),
      'validate_alphanumeric' =>__('Enter at least {0} characters','bootstrapped-login'),
      
      'checking_email' =>__('Checking email address','bootstrapped-login'),
      'sending_info' =>__('Sending user info, please wait...','bootstrapped-login'),
      'login_successfull' => __('Login successful, redirecting...','bootstrapped-login'),
      'login_error' =>__('Wrong username or password.','bootstrapped-login'),
      'validate_username' => __('This username is already taken.','bootstrapped-login'),
      'ajaxurl' => admin_url( 'admin-ajax.php'),
      'fbappid' => get_option('fb_appid'),
      'fbloginurl' => $facebook->getLoginUrl(array(
        'redirect_uri' => site_url().'/fblogin?r='.bootstrapped_login_full_path(),
        'scope' => 'email,user_birthday')
      )
    );
}

function bootstrapped_login_scripts() {
  wp_enqueue_script( 'jquery.validate', plugins_url( 'jquery.validate.min.js' , __FILE__ ), array( 'jquery') );
  wp_register_script( 'ajaxlogin', plugins_url( 'bootstraped-login.js' , __FILE__ ), array( 'jquery','jquery.validate','bootstrap' )  );
  wp_enqueue_script( 'jquery.validate.additional', plugins_url( 'additional-methods.min.js' , __FILE__ ), array( 'jquery.validate' )  );
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
    add_menu_page('bootstrapped', 'Bootstrapped Login', 'manage_options', 'bootstrapped_login_menu_settings', 'bootstrapped_login_menu_settings');
}

function bootstrapped_login_menu_settings(){
?>
<div class="wrap">

  <div id="icon-tools" class="icon32"><br></div><?php echo "<h2>".__('Bootstrapped Login settings')."</h2>";?>
    <form  method="post">
        <?php 
          if($_POST) {
            update_option( 'bootstrapped-login-fb-appid', $_POST['fb_appid'] );
            update_option( 'bootstrapped-login-fb-appsecret', $_POST['fb_appsecret'] );
            update_option( 'bootstrapped-login-fb', $_POST['fb'] );
            echo "settings saved";
          }
        ?>
  <h3>Facebook API keys</h3>
<table class="form-table">
<tr>
<th scope="row"><label for="fb">Enable Facebook login</label></th>
<td><input name="fb" type="checkbox" id="fb" value="1" <?php echo get_option('bootstrapped-login-fb') ? ' checked ':'' ?> /></td>
</tr>
<tr>
<th scope="row"><label for="appid">App ID</label></th>
<td><input name="fb_appid" type="text" id="fb_appid" value="<?php echo esc_attr( get_option('bootstrapped-login-fb-appid') ); ?>" class="regular-text" /></td>
</tr>
<tr>
<th scope="row"><label for="appsecret">App secret</label></th>
<td><input name="fb_appsecret" type="text" id="fb_appsecret" value="<?php echo esc_attr( get_option('bootstrapped-login-fb-appsecret') ); ?>" class="regular-text" /></td>
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