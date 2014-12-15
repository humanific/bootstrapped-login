<?php
/**
 * @package bootstrapped_carousel
 * @version 0.1
 */
/*
Plugin Name: Bootstrapped carousel
Plugin URI: https://github.com/humanific/bootstrapped-carousel
Description: Shortcode for displaying bootstrap carousels
Author: Francois Richir
Version: 0.1
Author URI: http://humanific.com
*/


require_once('facebook/facebook.php');


function ajax_login(){
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

function ajax_checkusername(){
    die( username_exists($_GET['username']) ? json_encode(false) : json_encode(true) ) ;
}

function ajax_register(){
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
      echo json_encode(array('register'=>true, 'message'=>'success','info'=>__('Signup successful','warp')));
    } else{
      $errors = array(
        'existing_user_login'=> __('This username is taken','warp'),
        'existing_user_email'=>__('This email is already registered','warp')
        );
      echo json_encode(array('register'=>false, 'message'=>'error', 'info'=>$errors[$user_id->get_error_code()]));
    }
    die();
}


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



function ajax_lostpassword(){
  global $wpdb;
  check_ajax_referer( 'ajax-pwd-nonce', 'security-pwd' );
  if(empty($_POST['email'])) {
    die("<div class='alert alert-danger'>".__('Please enter your Username or E-mail address','warp')."</div>");
  }
  //We shall SQL escape the input
  $user_input = $wpdb->prepare(trim($_POST['email']));
  
  if ( strpos($user_input, '@') ) {
    $user_data = get_user_by_email($user_input);
    if(empty($user_data) || $user_data->caps[administrator] == 1) { //delete the condition $user_data->caps[administrator] == 1, if you want to allow password reset for admins also
       die("<div class='alert alert-danger'>".__('Invalid E-mail address','warp')."</div>");
    }
  }
  else {
    $user_data = get_userdatabylogin($user_input);
    if(empty($user_data) || $user_data->caps[administrator] == 1) { //delete the condition $user_data->caps[administrator] == 1, if you want to allow password reset for admins also
       die("<div class='alert alert-danger'>".__('Invalid Username','warp')."</div>");
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
       die("<div class='alert alert-danger'>".__('Email failed to send for some unknown reason.','warp')."</div>");
    } else {
       die("<div class='alert alert-success'>".__('We have just sent you an email with Password reset instructions.','warp')."</div>");
    }

}



if(!is_user_logged_in()){
  add_action( 'wp_ajax_nopriv_ajaxlogin', 'ajax_login' );
  add_action( 'wp_ajax_nopriv_ajaxregister', 'ajax_register' );
  add_action( 'wp_ajax_nopriv_ajaxlostpassword', 'ajax_lostpassword' );
  add_action( 'wp_ajax_nopriv_ajax_fb_login', 'ajax_fb_login' );
  add_action( 'wp_ajax_nopriv_ajax_checkusername', 'ajax_checkusername' );
  add_action('wp_footer', 'bootsrapped_login_modal', 100);
}


function bootsrapped_login_modal(){ ?>
<div class="modal fade" id="login_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-body ">
          <div class="loginajax">
          <form id="lostpasswordform"  method="post"> 
            <h2><?php _e('Forgotten password','warp');?></h2>
          <input type="hidden" name="action" value="tg_pwd_reset" />
          <?php wp_nonce_field( 'ajax-pwd-nonce', 'security-pwd' ); ?>       
          
          
          <div class="form-group">
            <div id="passstatus"></div>
          <div>
          <input type="text" name="email" placeholder="<?php _e('Email','warp'); ?>" class="form-control required email" />
          </div>
          </div>
            
            <div class="form-group">
              <div>
                <input type="submit" name="submit" class="btn btn-primary btn-lg" value="<?php _e('Reset Password'); ?>"/>
              </div>
            </div>
          </form>


          <form id="signupform" action="" method="post"> 
            <a class="btn btn-default btn-fb fb_login btn-lg" href="#"><?php _e('Register with facebook','warp');?></a>
            <p><?php _e('or','warp')?></p>
            <div class="form-group">
              <div id="signupstatus"></div>
            <div >
              <input type="text" name="username" placeholder="<?php _e('Username','warp'); ?>" class="username form-control required alphanumeric" />
            </div>
            </div>
            <div class="form-group">
            <div >
            <input type="text" name="email" placeholder="<?php _e('Email','warp'); ?>" class="form-control required email " />
            </div>
            </div>

            <div class="form-group">
            <div>
            <input type="password" name="password" placeholder="<?php _e('Password','warp'); ?>" id="password" class="password form-control required" />
            </div>
            </div>
            <div class="form-group">
            <div>
            <input type="password" name="password2" placeholder="<?php _e('Repeat password','warp'); ?>" id="password2" class="form-control required" />
            </div>
            </div>
            <p class="small"><?php _e('By registering you agree to our <a href="http://www.facealacrise.be/conditions-generales-dutilisation/">terms and conditions</a>','warp'); ?></p>
            <?php wp_nonce_field( 'ajax-signup-nonce', 'security-signup' ); ?>
            
            <div class="form-group">
              <div>
                <input type="submit" name="submit" class="btn btn-primary btn-lg" value="<?php _e('Sign up','warp'); ?>"/>
              </div>
            </div>
          </form>


         <form id="loginform"  method="post">
            <a class="btn btn-default btn-fb fb_login  btn-lg" href="#"><?php _e('Login with facebook','warp');?></a>
            <p><?php _e('or','warp')?></p>
              
          <div class="form-group">
            <div id="loginstatus"></div>
          <div >
          <input type="text" name="username" id="username" placeholder="<?php _e('Email or username','warp'); ?>" class="form-control" />
          </div>
          </div>
          <div class="form-group">
          <div >
          <input type="password" name="password" id="password" placeholder="<?php _e('Password','warp'); ?>" class="form-control" />
          </div>
          </div>
          
          <div class="form-group">
          <div >
              <input class="btn btn-primary btn-lg" type="submit" value="<?php _e('Log in','warp');?>" name="submit">
              
          </div>
          </div>
              <?php wp_nonce_field( 'ajax-login-nonce', 'security' ); ?>
              
          <div class="form-group">
          <div>
              <hr>
              <a  href="/lostpassword" class="ajaxlostpassword"><?php _e('Forgotten password','warp');?></a>
              <hr>
              <?php _e('If you don\'t have a facealacrise.be account' ,'warp');?> <a href="/signup" class="ajaxsignup" ><?php _e('signup','warp');?></a>
          </div>
          </div>

      </form>
    </div>
      </div>
    </div>
  </div>
</div> 



<?php
  
}


function save_profile($user_id){
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


function get_user_profile($user_id){
  $usermetas = array('first_name' ,'last_name','city','country','postalcode','address','newsletter', 'children','usertitle','dob');
  $profile = array();
  foreach ($usermetas as $key) {
    $profile[$key] = get_user_meta( $user_id, $key , true );
  }
  return $profile;
}


function bootsrapped_profile_form(){
  if ( is_user_logged_in() ){ 
    $user_id = get_current_user_id(); 
    if($_POST && isset($_GET['profilesave'])) {
      save_profile($user_id);?>
      <div class="alert alert-success"><?php _e('Your profile has been updated','warp'); ?></div>
      <?php
    }
    profileform_html($user_id,get_user_profile($user_id));
  }else{
    if($_POST['username'] && isset($_GET['profilesave'])){
      $profile = get_transient( 'user_profile' );
      $userdata = array(
          'user_pass'    =>  wp_generate_password(),
          'user_email'  => $profile['email'],
          'user_login'    =>  $_POST['username']
      );
      $user_id = wp_insert_user( $userdata ) ;
      if( !is_wp_error($user_id) ) {
        save_profile($user_id);
        update_user_meta( $user_id, 'fbdata' , json_encode($profile) );
        update_user_meta( $user_id, 'fbid' ,$profile['fbid'] );
        wp_set_current_user( $user_id);
        wp_set_auth_cookie( $user_id );?>
      <div class="alert alert-success">
        <?php _e('Your account has been created','warp'); ?>
        <div class="spinner">
        <div class="bounce1"></div>
        <div class="bounce2"></div>
        <div class="bounce3"></div>
      </div>
      </div>
      <?php wp_redirect( home_url() ); exit; ?>
      <?php

      } else{
        $errors = array(
          'existing_user_login'=> __('This username is taken','warp'),
          'existing_user_email'=>__('This email is already registered','warp')
          );
        ?>
      <div class="alert alert-success"><?php echo $errors[$user_id->get_error_code()]; ?></div>
      <?php
      }
    } elseif(isset($_GET['facebook'])){
      $profile = get_transient( 'user_profile' );
      $profile['usertitle'] = $profile['gender'] == 'male' ? 'Mr' : 'Mrs' ;
      $profile['dob'] = $profile['birthday'] ;
      profileform_html(false, $profile,false, array('username' => true)); 
    }
  };
}

function profileform_html($user_id, $profile = false, $buttontext = false, $ad_fields = array(), $validate = true){
  if(!$buttontext) $buttontext =  $user_id ?  __('Save my profile','warp') : __('Create an account','warp') ;
  ?>
          <form class="form-horizontal"  role="form" id="profileform" method="POST" action="?profilesave">
          <?php if($ad_fields['username']) : ?>
            <div class="form-group">
            <label class="col-sm-4 control-label" for="username"><?php _e('Username','warp'); ?></label>
            <div class="col-sm-8">
            <input type="text" name="username" value="<?php if($profile) echo $profile['first_name'].$profile['last_name'] ;?>" class="username" />
            </div>
            </div>
          <?php endif; ?>
          <?php if($ad_fields['email']) : ?>
            <div class="form-group">
            <label class="col-sm-4 control-label" for="username"><?php _e('Email','warp'); ?></label>
            <div class="col-sm-8">
            <input type="text" name="email" class="email required form-control" />
            </div>
            </div>
          <?php endif; ?>
          
          <div class="form-group">
            <label class="col-sm-4 control-label" for="usertitle"><?php _e('Prefix','warp'); ?></label>
          <div class="col-sm-8">
            <select name="usertitle" class="form-control">
              <option  value="Mr" <?php echo ($profile['usertitle'] == 'Mr' ? 'selected' : '' ) ?>><?php _e('Mr.','warp'); ?></option>
              <option  value="Mrs" <?php echo ($profile['usertitle'] == 'Mrs' ? 'selected' : '' ) ?>><?php _e('Mrs.','warp'); ?></option>
              <option  value="Ms" <?php echo ($profile['usertitle'] == 'Ms' ? 'selected' : '' ) ?>><?php _e('Ms.','warp'); ?></option>
            </select>
          </div>
          </div>

          <div class="form-group">
          <label class="col-sm-4 control-label" for="first_name"><?php _e('First name','warp'); ?></label>
          <div class="col-sm-8">
          <input type="text" name="first_name" class="form-control" value="<?php echo $profile['first_name']?>" />
          </div>
          </div>

          <div class="form-group">
          <label class="col-sm-4 control-label" for="last_name"><?php _e('Last name','warp'); ?></label>
          <div class="col-sm-8">
          <input type="text" name="last_name" class="form-control"  value="<?php echo $profile['last_name']?>"/>
          </div>
          </div>

          <div class="form-group">
          
          <label class="col-sm-4 control-label" for="last_name"><?php _e('Date of birth','warp'); ?></label>
          <div class="col-sm-8 form-inline">
          <div class="input-group"><?php echo date_dropdown($profile['dob']);?></div>
          
          </div>
          </div>

          <div class="form-group">
          <label class="col-sm-4 control-label" for="address"><?php _e('Address','warp'); ?></label>
          <div class="col-sm-8">
          <input type="text" name="address"  value="<?php echo $profile['address']?>" class="form-control" />
          </div>
          </div>

           <div class="form-group">
          <label class="col-sm-4 control-label" for="postalcode"><?php _e('Postal code','warp'); ?></label>
          <div class="col-sm-8">
          <input type="text" name="postalcode"  value="<?php echo $profile['postalcode']?>" class="form-control" />
          </div>
          </div> 

           <div class="form-group">
          <label class="col-sm-4 control-label" for="city"><?php _e('City','warp'); ?></label>
          <div class="col-sm-8">
          <input type="text" name="city"  value="<?php echo $profile['city']?>" class="form-control" />
          </div>
          </div> 

           <div class="form-group">
          <label class="col-sm-4 control-label" for="country"><?php _e('Country','warp'); ?></label>
          <div class="col-sm-8">
          <?php 
          country_select($profile['country'] ? $profile['country'] : 'BE')
          ?>
          </div>
          </div>

          <div class="form-group">
          <label class="col-sm-4 control-label" for="children"><?php _e('Number of children','warp'); ?></label>
          <div class="col-sm-8">
          <select name="children" id="year_select" class="form-control">
            <?php 
            $ch = $profile['children'] ;
            for ($i=0; $i < 7; $i++) { 
              echo '<option'.($ch == $i ? ' selected' : '' ).'>'.$i.'</option>' ;
            } ?>
          </select>
          </div>
          </div> 

          <hr>
          <div class="form-group">
          <div class="col-sm-offset-4 col-sm-8">
          <input type="checkbox" name="newsletter" <?php echo $profile['newsletter'] ? 'checked' : '' ?>  /> <?php _e('Subscribe to the newsletter','warp'); ?>
          </div>
          </div>
          <hr>
            <div class="form-group">
              <div class="col-sm-offset-4 col-sm-8">
                <?php wp_nonce_field( 'profile-nonce', 'security' ); ?>
                <input type="submit" name="submit" class="btn btn-primary btn-lg" id="profilesubmit" value="<?php echo $buttontext; ?>"/>
              </div>
            </div>
          </form>
<?php if($validate): ?>
    <script>
      jQuery(function($){
         $("#profileform").validate({rules:{    
            username: {
              required: true,
              alphanumeric: true,
              remote: "<?php echo admin_url( 'admin-ajax.php')?>?action=ajax_checkusername",
              minlength: 2
          }}});
      })
      jQuery.extend(jQuery.validator.messages, {
            required: "<?php _e("This field is mandatory",'warp'); ?>", 
            email: "<?php _e('Please check this email address','warp'); ?>",
            equalTo:"<?php _e('Please enter the same value again.','warp');?>",
            remote:"<?php _e('This username is already taken.','warp');?>",
            alphanumeric:"<?php _e('Letters, numbers, and underscores only please','warp');?>"
            
       });
    </script>

<?php endif;

}


function profileform_shortcode( $atts, $content = null ) {
   ob_start();
   bootsrapped_profile_form();
  return ob_get_clean();
}

add_shortcode( 'profileform', 'profileform_shortcode' );


function bootsrapped_reset_password(){
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
        return "<div class='alert alert-danger'>".__('Email failed to send for some unknown reason.','warp')."</div>";
      }
      else {
        return "<div class='alert alert-success'>".__('A new password has been sent to your email address','warp')."</div>";
      }
    } 
    else exit('Not a Valid Key.');
}



function bootsrapped_pwd_form (){
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
          <div class="alert alert-success"><?php _e('Your password has been updated','warp'); ?></div>
          <?php
      } else{
    ?>
        <form class="form-horizontal"  role="form" id="pwdform" method="POST" action="">
        <div class="form-group">
        <label class="col-sm-6 control-label" for="password"><?php _e('Password','warp'); ?></label>
        <div class="col-sm-6">
        <input type="password" name="password" id="password" class="form-control required" />
        </div>
        </div>
        <div class="form-group">
        <label class="col-sm-6 control-label" for="password2"><?php _e('Repeat password','warp'); ?></label>
        <div class="col-sm-6">
        <input type="password" name="password2" id="password2" class="form-control required" />
        </div>
        </div>
          <div class="form-group">
              <div class="col-sm-offset-6 col-sm-6">
                <input type="submit" name="submit" class="btn btn-primary" value="<?php _e('Update password','warp'); ?>"/>
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
            required: "<?php _e("This field is mandatory",'warp'); ?>", 
            email: "<?php _e('Please check this email address','warp'); ?>",
            equalTo:"<?php _e('Please enter the same value again.','warp');?>",
            minlength: jQuery.format("<?php _e('Enter at least {0} characters','warp');?>")

       });
    </script>

  <?php    }

    } 
    else exit('Not a Valid Key.');
}


function bootsrapped_change_pwd_form (){
    $user = wp_get_current_user();
    if($user){
      if($_POST['password'] && strlen($_POST['password'])>4 ) {
        if(wp_check_password( $_POST['oldpassword'], $user->data->user_pass, $user->ID)){
          wp_set_password( $_POST['password'] , $user->ID );
          ?>
          <div class="alert alert-success"><?php _e('Your password has been changed','warp'); ?></div>
          <?php
        }else{
          ?>
          <div class="alert alert-danger"><?php _e('Wrong password','warp'); ?></div>
          <?php
          $passerror = true;
        }
      } 

      if($passerror || !isset($_POST['password'])){
    ?>
        <form class="form-horizontal"  role="form" id="pwdform" method="POST" action="?savepass">
        <div class="form-group">
        <label class="col-sm-6 control-label" for="oldpassword"><?php _e('Old password','warp'); ?></label>
        <div class="col-sm-6">
        <input type="oldpassword" name="oldpassword" id="oldpassword" class="form-control required" />
        </div>
        </div>
        <div class="form-group">
        <label class="col-sm-6 control-label" for="password"><?php _e('Password','warp'); ?></label>
        <div class="col-sm-6">
        <input type="password" name="password" id="password" class="form-control required" />
        </div>
        </div>
        <div class="form-group">
        <label class="col-sm-6 control-label" for="password2"><?php _e('Repeat password','warp'); ?></label>
        <div class="col-sm-6">
        <input type="password" name="password2" id="password2" class="form-control required" />
        </div>
        </div>
          <div class="form-group">
              <div class="col-sm-offset-6 col-sm-6">
                <input type="submit" name="submit" class="btn btn-primary" value="<?php _e('Update password','warp'); ?>"/>
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
            required: "<?php _e("This field is mandatory",'warp'); ?>", 
            email: "<?php _e('Please check this email address','warp'); ?>",
            equalTo:"<?php _e('Please enter the same value again.','warp');?>",
            minlength: jQuery.format("<?php _e('Enter at least {0} characters','warp');?>")

       });
    </script>
 
  <?php    } }
}


function changepasswordform_shortcode( $atts, $content = null ) {
   ob_start();
   bootsrapped_change_pwd_form();
  return ob_get_clean();
}

add_shortcode( 'changepasswordform', 'changepasswordform_shortcode' );



function passwordform_shortcode( $atts, $content = null ) {
   ob_start();
   bootsrapped_pwd_form();
  return ob_get_clean();
}

add_shortcode( 'passwordform', 'passwordform_shortcode' );








function usermenu_shortcode( $atts, $content = null ) {
   ob_start();?>

      <?php if ( is_user_logged_in() ):
        global $current_user;
        get_currentuserinfo();
        $fb = get_user_meta( $current_user->ID, 'fbid', true );
        if($fb) echo '<img src="https://graph.facebook.com/'.$fb.'/picture" width="15" height="15" alt="icon" class="fbimage"/> ';
        echo __('Hi','warp').' '.$current_user->display_name;?> &nbsp;
        
        <a href="/profile" title="<?php _e('My profile','sdvu');?>"><?php _e('My profile','warp');?></a> &nbsp;
        <a href="<?php echo wp_logout_url( full_path() ); ?>" title="<?php _e('Log out','warp');?>"><?php _e('Log out','warp');?></a>
      <?php else:?>
      <a href="#"  class="ajaxlogin" ><?php _e('Log in','warp');?></a> &nbsp;
      <a href="#"  class="ajaxsignup" ><?php _e('Register','warp');?></a>
      <?php endif;?>

   <?php
  return ob_get_clean();
}

add_shortcode( 'usermenu', 'usermenu_shortcode' );

add_filter('widget_text', 'do_shortcode');


function bootsrapped_forget_password_form(){
      if($_REQUEST['action'] == "tg_pwd_reset"){
        $msg = bootsrapped_ask_password_reset();
        die($msg);
      }
      if(isset($_GET['key']) && $_GET['action'] == "reset_pwd") {
        echo bootsrapped_reset_password();
      } else { ?>
    <?php }
  }



function bootsrapped_login_signup_vars(){
  $facebook = new Facebook(array(
    'appId'  => get_option('fb_appid'),
    'secret' => get_option('fb_appsecret')
  ));

  return array( 'validate_required' =>__("This field is mandatory",'warp'),
      'validate_email' =>__('Please check this email address','warp'),
      'validate_equalTo' =>__('Please enter the same value again.','warp'),
      'validate_minlength' =>__('Enter at least {0} characters','warp'),
      'validate_alphanumeric' =>__('Enter at least {0} characters','warp'),
      
      'checking_email' =>__('Checking email address','warp'),
      'sending_info' =>__('Sending user info, please wait...','warp'),
      'login_successfull' => __('Login successful, redirecting...','warp'),
      'login_error' =>__('Wrong username or password.','warp'),
      'validate_username' => __('This username is already taken.','warp'),
      'ajaxurl' => admin_url( 'admin-ajax.php'),
      'fbappid' => get_option('fb_appid'),
      'fbloginurl' => $facebook->getLoginUrl(array(
        'redirect_uri' => site_url().'/fblogin?r='.full_path(),
        'scope' => 'email,user_birthday')
      )
    );
}

function bootsrapped_scripts() {

  wp_enqueue_script( 'validate', get_template_directory_uri() . '/js/validate/jquery.validate.min.js' );
  wp_register_script( 'ajaxlogin', get_template_directory_uri() . '/js/ajaxlogin.js' );
  wp_localize_script('ajaxlogin', 'ajaxlogin', bootsrapped_login_signup_vars());
  wp_enqueue_script( 'ajaxlogin');
}

function full_path(){
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

add_action( 'wp_enqueue_scripts', 'bootsrapped_scripts' );









  function date_dropdown($selected){
    if($selected){
      list($smonth,$sday, $syear) = explode('/', $selected);
    }
    $year_limit = 0;
        $html_output = '    <div id="date_select"  >'."\n";
        $html_output .= '           <select name="date_day" id="day_select" class="form-control col-xs-3">'."\n";
            for ($day = 1; $day <= 31; $day++) {
                $html_output .= '               <option '. ($sday == $day ? 'selected' : '') .'>' . $day . '</option>'."\n";
            }
        $html_output .= '           </select>'."\n";
        $html_output .= '           <select name="date_month" id="month_select"  class="form-control col-xs-5">'."\n";
            for ($month = 1; $month <= 12; $month++) {
                $html_output .= '               <option value="' . $month . '" '. ($smonth == $month ? 'selected' : '') .'>' . date_i18n( 'F', mktime(0, 0, 0, $month, 1, 2000)) . '</option>'."\n";
            }
        $html_output .= '           </select>'."\n";
        $html_output .= '           <select name="date_year" id="year_select"  class="form-control col-xs-4">'."\n";
            for ($year = 1900; $year <= (date("Y") - $year_limit); $year++) {
                $html_output .= '               <option '. ($syear == $year ? 'selected' : '') .'>' . $year . '</option>'."\n";
            }
        $html_output .= '           </select>'."\n";
        $html_output .= '   </div>'."\n";
    return $html_output;
}





function country_select($selected){
    $countries = array(
      "GB" => "United Kingdom",
      "US" => "United States",
      "AF" => "Afghanistan",
      "AL" => "Albania",
      "DZ" => "Algeria",
      "AS" => "American Samoa",
      "AD" => "Andorra",
      "AO" => "Angola",
      "AI" => "Anguilla",
      "AQ" => "Antarctica",
      "AG" => "Antigua And Barbuda",
      "AR" => "Argentina",
      "AM" => "Armenia",
      "AW" => "Aruba",
      "AU" => "Australia",
      "AT" => "Austria",
      "AZ" => "Azerbaijan",
      "BS" => "Bahamas",
      "BH" => "Bahrain",
      "BD" => "Bangladesh",
      "BB" => "Barbados",
      "BY" => "Belarus",
      "BE" => __("Belgium",'warp'),
      "BZ" => "Belize",
      "BJ" => "Benin",
      "BM" => "Bermuda",
      "BT" => "Bhutan",
      "BO" => "Bolivia",
      "BA" => "Bosnia And Herzegowina",
      "BW" => "Botswana",
      "BV" => "Bouvet Island",
      "BR" => "Brazil",
      "IO" => "British Indian Ocean Territory",
      "BN" => "Brunei Darussalam",
      "BG" => "Bulgaria",
      "BF" => "Burkina Faso",
      "BI" => "Burundi",
      "KH" => "Cambodia",
      "CM" => "Cameroon",
      "CA" => "Canada",
      "CV" => "Cape Verde",
      "KY" => "Cayman Islands",
      "CF" => "Central African Republic",
      "TD" => "Chad",
      "CL" => "Chile",
      "CN" => "China",
      "CX" => "Christmas Island",
      "CC" => "Cocos (Keeling) Islands",
      "CO" => "Colombia",
      "KM" => "Comoros",
      "CG" => "Congo",
      "CD" => "Congo, The Democratic Republic Of The",
      "CK" => "Cook Islands",
      "CR" => "Costa Rica",
      "CI" => "Cote D'Ivoire",
      "HR" => "Croatia (Local Name: Hrvatska)",
      "CU" => "Cuba",
      "CY" => "Cyprus",
      "CZ" => "Czech Republic",
      "DK" => "Denmark",
      "DJ" => "Djibouti",
      "DM" => "Dominica",
      "DO" => "Dominican Republic",
      "TP" => "East Timor",
      "EC" => "Ecuador",
      "EG" => "Egypt",
      "SV" => "El Salvador",
      "GQ" => "Equatorial Guinea",
      "ER" => "Eritrea",
      "EE" => "Estonia",
      "ET" => "Ethiopia",
      "FK" => "Falkland Islands (Malvinas)",
      "FO" => "Faroe Islands",
      "FJ" => "Fiji",
      "FI" => "Finland",
      "FR" => "France",
      "FX" => "France, Metropolitan",
      "GF" => "French Guiana",
      "PF" => "French Polynesia",
      "TF" => "French Southern Territories",
      "GA" => "Gabon",
      "GM" => "Gambia",
      "GE" => "Georgia",
      "DE" => "Germany",
      "GH" => "Ghana",
      "GI" => "Gibraltar",
      "GR" => "Greece",
      "GL" => "Greenland",
      "GD" => "Grenada",
      "GP" => "Guadeloupe",
      "GU" => "Guam",
      "GT" => "Guatemala",
      "GN" => "Guinea",
      "GW" => "Guinea-Bissau",
      "GY" => "Guyana",
      "HT" => "Haiti",
      "HM" => "Heard And Mc Donald Islands",
      "VA" => "Holy See (Vatican City State)",
      "HN" => "Honduras",
      "HK" => "Hong Kong",
      "HU" => "Hungary",
      "IS" => "Iceland",
      "IN" => "India",
      "ID" => "Indonesia",
      "IR" => "Iran (Islamic Republic Of)",
      "IQ" => "Iraq",
      "IE" => "Ireland",
      "IL" => "Israel",
      "IT" => "Italy",
      "JM" => "Jamaica",
      "JP" => "Japan",
      "JO" => "Jordan",
      "KZ" => "Kazakhstan",
      "KE" => "Kenya",
      "KI" => "Kiribati",
      "KP" => "Korea, Democratic People's Republic Of",
      "KR" => "Korea, Republic Of",
      "KW" => "Kuwait",
      "KG" => "Kyrgyzstan",
      "LA" => "Lao People's Democratic Republic",
      "LV" => "Latvia",
      "LB" => "Lebanon",
      "LS" => "Lesotho",
      "LR" => "Liberia",
      "LY" => "Libyan Arab Jamahiriya",
      "LI" => "Liechtenstein",
      "LT" => "Lithuania",
      "LU" => "Luxembourg",
      "MO" => "Macau",
      "MK" => "Macedonia, Former Yugoslav Republic Of",
      "MG" => "Madagascar",
      "MW" => "Malawi",
      "MY" => "Malaysia",
      "MV" => "Maldives",
      "ML" => "Mali",
      "MT" => "Malta",
      "MH" => "Marshall Islands",
      "MQ" => "Martinique",
      "MR" => "Mauritania",
      "MU" => "Mauritius",
      "YT" => "Mayotte",
      "MX" => "Mexico",
      "FM" => "Micronesia, Federated States Of",
      "MD" => "Moldova, Republic Of",
      "MC" => "Monaco",
      "MN" => "Mongolia",
      "MS" => "Montserrat",
      "MA" => "Morocco",
      "MZ" => "Mozambique",
      "MM" => "Myanmar",
      "NA" => "Namibia",
      "NR" => "Nauru",
      "NP" => "Nepal",
      "NL" => "Netherlands",
      "AN" => "Netherlands Antilles",
      "NC" => "New Caledonia",
      "NZ" => "New Zealand",
      "NI" => "Nicaragua",
      "NE" => "Niger",
      "NG" => "Nigeria",
      "NU" => "Niue",
      "NF" => "Norfolk Island",
      "MP" => "Northern Mariana Islands",
      "NO" => "Norway",
      "OM" => "Oman",
      "PK" => "Pakistan",
      "PW" => "Palau",
      "PA" => "Panama",
      "PG" => "Papua New Guinea",
      "PY" => "Paraguay",
      "PE" => "Peru",
      "PH" => "Philippines",
      "PN" => "Pitcairn",
      "PL" => "Poland",
      "PT" => "Portugal",
      "PR" => "Puerto Rico",
      "QA" => "Qatar",
      "RE" => "Reunion",
      "RO" => "Romania",
      "RU" => "Russian Federation",
      "RW" => "Rwanda",
      "KN" => "Saint Kitts And Nevis",
      "LC" => "Saint Lucia",
      "VC" => "Saint Vincent And The Grenadines",
      "WS" => "Samoa",
      "SM" => "San Marino",
      "ST" => "Sao Tome And Principe",
      "SA" => "Saudi Arabia",
      "SN" => "Senegal",
      "SC" => "Seychelles",
      "SL" => "Sierra Leone",
      "SG" => "Singapore",
      "SK" => "Slovakia (Slovak Republic)",
      "SI" => "Slovenia",
      "SB" => "Solomon Islands",
      "SO" => "Somalia",
      "ZA" => "South Africa",
      "GS" => "South Georgia, South Sandwich Islands",
      "ES" => "Spain",
      "LK" => "Sri Lanka",
      "SH" => "St. Helena",
      "PM" => "St. Pierre And Miquelon",
      "SD" => "Sudan",
      "SR" => "Suriname",
      "SJ" => "Svalbard And Jan Mayen Islands",
      "SZ" => "Swaziland",
      "SE" => "Sweden",
      "CH" => "Switzerland",
      "SY" => "Syrian Arab Republic",
      "TW" => "Taiwan",
      "TJ" => "Tajikistan",
      "TZ" => "Tanzania, United Republic Of",
      "TH" => "Thailand",
      "TG" => "Togo",
      "TK" => "Tokelau",
      "TO" => "Tonga",
      "TT" => "Trinidad And Tobago",
      "TN" => "Tunisia",
      "TR" => "Turkey",
      "TM" => "Turkmenistan",
      "TC" => "Turks And Caicos Islands",
      "TV" => "Tuvalu",
      "UG" => "Uganda",
      "UA" => "Ukraine",
      "AE" => "United Arab Emirates",
      "UM" => "United States Minor Outlying Islands",
      "UY" => "Uruguay",
      "UZ" => "Uzbekistan",
      "VU" => "Vanuatu",
      "VE" => "Venezuela",
      "VN" => "Viet Nam",
      "VG" => "Virgin Islands (British)",
      "VI" => "Virgin Islands (U.S.)",
      "WF" => "Wallis And Futuna Islands",
      "EH" => "Western Sahara",
      "YE" => "Yemen",
      "YU" => "Yugoslavia",
      "ZM" => "Zambia",
      "ZW" => "Zimbabwe"
    );
   $htm = '<select name="country"  class="form-control">';
   foreach ($countries as $key => $country) {
     $htm .='<option value="'.$key.'" '.($selected == $key ? 'selected' : '' ).'>'.__($country,'').'</option>';
   }
   $htm .= '</select>';
    echo $htm;
}
?>

?>