jQuery(function($) {
        var msgs = {
          error:ajaxlogin.login_error,
          success:ajaxlogin.login_successfull
        }
        var redirect = null;
		console.log($('form#loginform'));
        $('form#loginform').submit(function(e){
		console.log($('submit'));
              var submits = $(this).find('input[type="submit"]')
              var loginstatus = $(this).find('.loginstatus');
              loginstatus.show().text(ajaxlogin.sending_info).attr( "class", "alert alert-info" );
              submits.attr('disabled','disabled');

              var nonce = $('#ajaxloginref').val();
              $.ajax({
                  type: 'POST',
                  dataType: 'json',
                  url: ajaxlogin.ajaxurl,
                  data: {
                      'action': 'ajaxlogin', 
                      'username': $(this).find('input[name="username"]').val(),
                      'password': $(this).find('input[name="password"]').val(),
                      'nonce': ajaxlogin.login_nonce },
                  success: function(data){
                    loginstatus.show().text(msgs[data.message]);
                    if (data.loggedin == true){
                        if( redirect ) window.location = redirect;
                        else window.location.reload();
                        loginstatus.attr( "class", "alert alert-success" );
                    }else{
                      loginstatus.attr( "class", "alert alert-danger" );
                      submits.removeAttr('disabled');
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
                'security-pwd': $('form#lostpasswordform #security-pwd').val(),
                'lang': ajaxlogin.lang},

              success: function(data){
                $('#passstatus').html(data);
                $('#lostpasswordform input[type="submit"]').removeAttr('disabled');
              }
            });
            return false;
          }
        });

        $('.ajaxlogin').click(function(e){
          var r = $(this).attr('data-redirect');
          if(r) redirect = r;
          showModal('loginform');
          e.preventDefault();
        })


        document.ajaxlogin = function(){
          showModal('loginform');
        }

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
      if($.validator.messages){
        $.extend($.validator.messages, {
              required: ajaxlogin.validate_required,
              email: ajaxlogin.validate_email,
              equalTo: ajaxlogin.validate_equalTo,
              minlength: $.validator.format(ajaxlogin.validate_minlength),
              remote : ajaxlogin.validate_username,
              alphanumeric : ajaxlogin.validate_alphanumeric
         });

      }


      $('.fb_login').click(function(e){
        window.location.href = ajaxlogin.fbloginurl;
        e.preventDefault();
      })


});
