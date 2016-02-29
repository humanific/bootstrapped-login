<?php

get_header(); ?>
  <div class="container">
    <div class="row">
      <div class="col-sm-8 col-sm-offset-2 text-center">
      <?php

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
          <h1><?php _e('Reset your password','bootstrapped-login'); ?></h1>
          <div class="alert alert-success"><?php _e('Your password has been updated','bootstrapped-login'); ?></div>
          <?php
      } else{
    ?>
      <h1><?php _e('Reset your password','bootstrapped-login'); ?></h1>
      <div class="panel panel-default">
      <div class="panel-heading"><?php _e('Choose a new password','bootstrapped-login'); ?></div>
      <div class="panel-body text-left">
        <h1 class="text-center"></h1>
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

</div>
</div>
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

  <?php
    }
  }
  else _e('Not a Valid Key.','bootstrapped-login');


       ?>
      </div>
    </div>
  </div><!-- .container -->



    <?php get_footer(); ?>
