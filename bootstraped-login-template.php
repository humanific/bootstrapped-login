<div class="modal fade" id="login_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-body ">
          <div class="loginajax">
          <form id="lostpasswordform"  method="post"> 
            <h2><?php _e('Forgotten password','bootstrapped-login');?></h2>
          <input type="hidden" name="action" value="tg_pwd_reset" />
          <?php wp_nonce_field( 'ajax-pwd-nonce', 'security-pwd' ); ?>       
          
          
          <div class="form-group">
            <div id="passstatus"></div>
          <div>
          <input type="text" name="email" placeholder="<?php _e('Email','bootstrapped-login'); ?>" class="form-control required email" />
          </div>
          </div>
            
            <div class="form-group">
              <div>
                <input type="submit" name="submit" class="btn btn-primary btn-lg" value="<?php _e('Reset Password'); ?>"/>
              </div>
            </div>
          </form>


          <form id="signupform" action="" method="post"> 
            <?php if(get_option('bootstrapped-login-fb')) : ?>
            <a class="btn btn-default btn-fb fb_login btn-lg" href="#"><?php _e('Register with facebook','bootstrapped-login');?></a>
            <p><?php _e('or','bootstrapped-login')?></p>
            <?php else: ?>
            <h3>Register</h3>
            <?php endif ?>

            <div class="form-group">
              <div id="signupstatus"></div>
            <div >
              <input type="text" name="username" placeholder="<?php _e('Username','bootstrapped-login'); ?>" class="username form-control required alphanumeric" />
            </div>
            </div>
            <div class="form-group">
            <div >
            <input type="text" name="email" placeholder="<?php _e('Email','bootstrapped-login'); ?>" class="form-control required email " />
            </div>
            </div>

            <div class="form-group">
            <div>
            <input type="password" name="password" placeholder="<?php _e('Password','bootstrapped-login'); ?>" id="password" class="password form-control required" />
            </div>
            </div>
            <div class="form-group">
            <div>
            <input type="password" name="password2" placeholder="<?php _e('Repeat password','bootstrapped-login'); ?>" id="password2" class="form-control required" />
            </div>
            </div>
            <?php wp_nonce_field( 'ajax-signup-nonce', 'security-signup' ); ?>
            
            <div class="form-group">
              <div>
                <input type="submit" name="submit" class="btn btn-primary btn-lg" value="<?php _e('Sign up','bootstrapped-login'); ?>"/>
              </div>
            </div>
          </form>


         <form id="loginform"  method="post">
            <?php if(get_option('bootstrapped-login-fb')) : ?>
            <a class="btn btn-default btn-fb fb_login btn-lg" href="#"><?php _e('Login with facebook','bootstrapped-login');?></a>
            <p><?php _e('or','bootstrapped-login')?></p>
            <?php else: ?>
            <h3>Login</h3>
            <?php endif ?>
              
          <div class="form-group">
            <div id="loginstatus"></div>
          <div >
          <input type="text" name="username" id="username" placeholder="<?php _e('Email or username','bootstrapped-login'); ?>" class="form-control" />
          </div>
          </div>
          <div class="form-group">
          <div >
          <input type="password" name="password" id="password" placeholder="<?php _e('Password','bootstrapped-login'); ?>" class="form-control" />
          </div>
          </div>
          
          <div class="form-group">
          <div >
              <input class="btn btn-primary btn-lg" type="submit" value="<?php _e('Log in','bootstrapped-login');?>" name="submit">
              
          </div>
          </div>
              <?php wp_nonce_field( 'ajax-login-nonce', 'security' ); ?>
              
          <div class="form-group">
          <div>
              <hr>
              <a  href="/lostpassword" class="ajaxlostpassword"><?php _e('Forgotten password','bootstrapped-login');?></a>
              <hr>
              <?php _e('If you don\'t have an account yet, please' ,'bootstrapped-login');?> <a href="/signup" class="ajaxsignup" ><?php _e('signup','bootstrapped-login');?></a>
          </div>
          </div>

      </form>
    </div>
      </div>
    </div>
  </div>
</div> 