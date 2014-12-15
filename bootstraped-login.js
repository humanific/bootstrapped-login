jQuery(function($) {
        var msgs = {
          error:ajaxlogin.login_error, 
          success:ajaxlogin.login_successfull
        }

        $('form#loginform').on('submit', function(e){
              $('#loginform input[type="submit"]').attr('disabled','disabled');
              $('#loginstatus').show().text(ajaxlogin.sending_info).attr( "class", "alert alert-info" );
              $.ajax({
                  type: 'POST',
                  dataType: 'json',
                  url: ajaxlogin.ajaxurl,
                  data: { 
                      'action': 'ajaxlogin', //calls wp_ajax_nopriv_ajaxlogin
                      'username': $('form#loginform #username').val(), 
                      'password': $('form#loginform #password').val(), 
                      'security': $('form#loginform #security').val() },
                  success: function(data){
                      $('#loginstatus').show().text(msgs[data.message]);
                      if (data.loggedin == true){
                          window.location.reload();
                          $('#loginstatus').attr( "class", "alert alert-success" );
                      }else{
                        $('#loginstatus').attr( "class", "alert alert-danger" );
                        $('#loginform input[type="submit"]').removeAttr('disabled');
                      }
                  }
              });
            
            e.preventDefault();
        });
        

        $('form#signupform').on('submit', function(e){
            if($("#signupform").valid()){
              $('#signupform input[type="submit"]').attr('disabled','disabled');
              $('#signupstatus').show().text(ajaxlogin.sending_info).attr( "class", "alert alert-info" );
              $.ajax({
                  type: 'POST',
                  dataType: 'json',
                  url: ajaxlogin.ajaxurl,
                  data: { 
                      'action': 'ajaxregister', 
                      'username': $('form#signupform .username').val(),
                      'email': $('form#signupform .email').val(), 
                      'password': $('form#signupform .password').val(), 
                      'security-signup': $('form#signupform #security-signup').val() },
                  success: function(data){
                      $('#signupstatus').text(data.info);
                      if (data.register == true){
                          location.reload();
                          $('#signupstatus').attr( "class", "alert alert-success" );
                      }else{
                        $('#signupstatus').attr( "class", "alert alert-danger" );
                        $('#signupform input[type="submit"]').removeAttr('disabled');
                      }
                  }
              });
            }
            e.preventDefault();
        });
        
        $("#lostpasswordform").on('submit', function(e){
          if($("#lostpasswordform").valid()){
            $('#lostpasswordform input[type="submit"]').attr('disabled','disabled');
            $('#passstatus').text(ajaxlogin.checking_email+"...")
            $.ajax({
              type: "POST",
              url: ajaxlogin.ajaxurl,
              data: {
                'action': 'ajaxlostpassword',
                'email':$("#lostpasswordform .email").val(),
                'security-pwd': $('form#lostpasswordform #security-pwd').val() },
              success: function(data){
                $('#passstatus').html(data);
                $('#lostpasswordform input[type="submit"]').removeAttr('disabled');
              }
            });
            return false;
          }
        });

        $('.ajaxlogin').click(function(e){
          showModal('loginform');
           e.preventDefault();
        })

        $('.ajaxsignup').click(function(e){
          showModal('signupform');
           e.preventDefault();
        })

        $('.ajaxlostpassword').click(function(e){
          showModal('lostpasswordform');
          e.preventDefault();
        })

        function showModal(div){
          try {
              $('#login_modal').modal('show')
          }
          catch(err) {
             console.log('no bootstrap '+err);
            $('#login_modal').show();
            var $el = $('#login_modal #'+div).fadeIn();
            $('#login_modal .modal-dialog').css({
                opacity:1,
                position : 'absolute',
                display:'block',
                left: ($(window).width() - $el.width()) / 2,
                top: (($(window).height() - $el.height()) / 2)-30
            });
          } 
          

          $('#login_modal form').hide();
          var $el = $('#login_modal #'+div).fadeIn();
        }


        $('.ajaxcancel').click(function(e){
          $('#login_modal').hide();
           e.preventDefault();
        })
        

        $('#login_modal').click(function(e){
          if($(e.target).parents('.modal-dialog').length == 0) {
            $('#login_modal').hide();
          }
        })



     $("#lostpasswordform").validate({errorClass: "text-danger"});
     $("#signupform").validate({
        errorClass: "text-danger",
        rules: {
          username: {
              required: true,
              alphanumeric: true,
              remote: ajaxlogin.ajaxurl+"?action=ajax_checkusername",
              minlength: 2
            },
          password: {required:true,minlength: 4},
          password2: {
            equalTo: "#signupform .password"
          }
        }
      });

      jQuery.extend(jQuery.validator.messages, {
            required: ajaxlogin.validate_required, 
            email: ajaxlogin.validate_email, 
            equalTo: ajaxlogin.validate_equalTo, 
            minlength: jQuery.validator.format(ajaxlogin.validate_minlength),
            remote : ajaxlogin.validate_username,
            alphanumeric : ajaxlogin.validate_alphanumeric
       });


$('.fb_login').click(function(e){
 window.location.href = ajaxlogin.fbloginurl;
 e.preventDefault();
})







      });



