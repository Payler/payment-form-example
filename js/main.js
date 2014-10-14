  jQuery(function($){
    
    var card_type;
    var card_expired;
    var card_cvc;
    var card_num;
    
    // Забираем значения ошибок из скрытого поля
    var error_param = $('#error_param').attr('data-error-param');
    var error_value = $('#error_param').attr('data-error-value');

    
    // 20140919: Исправление бага с кнопкой на айфоне
    $('input').on('focus', function(){
      $('.payler-action-button').addClass('static');
    });
    $('input').on('blur', function(){
      $('.payler-action-button').removeClass('static');
    });
    
    
    
    $('#PaylerCardNum').payment('formatCardNumber');
    $('.cc-exp').payment('formatCardExpiry');
    $('.cc-cvc').payment('formatCardCVC');
    
    $('#PaylerCardNum').val($.payment.formatCardNumber(window.params_card["card_number"]));
    $('#PaylerCardNum').each(check_cardnumber_field);

    // Получает число в виде строки на входе и возвращает строку с двухзначным числом.
    // "5" -> "05"
    // "18" -> "18"
    // "2018" -> "2018"
    function get_two_digit_string_number(str_value) {
      value = parseInt(str_value);
      if(value >= 0 && value < 10) {
        return "0" + value.toString();
      }
      return str_value;
    }

    var exp_month = window.params_card["expired_month"];
    var exp_year = window.params_card["expired_year"];

    if(exp_month != "" && exp_year != "") {
      $('#PaylerExpired').val(get_two_digit_string_number(exp_month) + " / " + get_two_digit_string_number(exp_year));
      $('#PaylerExpired').each(expire_field_keyup_change);
      $('#PaylerExpired').each(expire_field_on_blur);
    }

    // Подстановка имени владельца, присланного сервером.
    // Если имя не было введено прошлый раз, то сервер вернёт NONAME.
    // Проверяем чтобы не подставлять его.
    var cardholder = window.params_card["card_holder"];
    if(cardholder != "NONAME") {
      $('#PaylerCardholder').val(cardholder);
      //$('#PaylerCardholder').each(postprocess_cardholder_field);
    }

    $('.trigger-help').on('focus', function(){
      if( card_type == 'amex' ) {
        $('.popup4').css('display', 'none');
        $('.popup5').css('display', 'block');
      }
      else {
        $('.popup4').css('display', 'block');
        $('.popup5').css('display', 'none');
      }
      
      $(this).siblings('.help-popup').addClass('show');
    })

    
    $('.trigger-help').on('blur', function(){
      $(this).siblings('.help-popup').removeClass('show');
    });

    $('.payler-error-popup .close').on('click',function(){
      $(this).parent().addClass('hide');
    })

    $('#PaylerCardNum').on('keypress change blur', check_cardnumber_field);

    function check_cardnumber_field() {
      card_type = $.payment.cardType( $(this).val() );
      $('.cards .sprite').removeClass('active');
      $(this).parent().parent().removeClass('error');
      
      if( card_type ) {
        $('.cards').find('.'+card_type).addClass('active');
      }
      
      card_num = $('.cc-number').val();
      check_all_fields();
    }
    
    $('#PaylerCardNum').on('paste',function(e){
      setTimeout(function (){
        card_type = $.payment.cardType( $(e.currentTarget).val() );
        $('.cards .sprite').removeClass('active');
        $(this).parent().parent().removeClass('error');
        if( card_type ) {
          $('.cards').find('.'+card_type).addClass('active');
        }
        card_num = $('.cc-number').val();
        check_all_fields();
      },0);
    });
    
    $('#PaylerExpired').on('keyup change', expire_field_keyup_change);

    function expire_field_keyup_change() {
      card_expired = $.payment.validateCardExpiry($('.cc-exp').payment('cardExpiryVal'));
      check_all_fields();
    }
    
    $('#PaylerExpired').on('blur', expire_field_on_blur);

    function expire_field_on_blur() {
      card_expired = $.payment.validateCardExpiry($('.cc-exp').payment('cardExpiryVal'));
      $(this).parent().parent().removeClass('error');
      
      if( !card_expired ) {
        $(this).parent().parent().addClass('error');
      }
      check_all_fields();
    }
    
    // Маска для кардхолдера - допускаются только латинские буквы и цифры.
    // Внимание! Используется измененный jquery.mask.min.js, не обновлять его, 
    // иначе перестанут вводится пробелы!
    $('#PaylerCardholder').mask('AAAAAAAAAAAAAAAAAAAAAAAAAA');
    /*$('#PaylerCardholder').on('keyup', postprocess_cardholder_field);

    function postprocess_cardholder_field(e) {
      if( e.keyCode != 17 && e.keyCode != 91 && e.keyCode != 93 ) {
        var inputValue = $('#PaylerCardholder').val();
        $('#PaylerCardholder').val(inputValue.replace(/[^a-zA-Z0-9 ]/g, ''));
        this.value = this.value.toUpperCase();
      }
    }*/
    
    $('#PaylerCode').on('keyup change', function(){
       card_cvc = $.payment.validateCardCVC($('.cc-cvc').val(), card_type);
       check_all_fields();
    })
    
    $('#PaylerCode').on('blur',function(){
       card_cvc = $.payment.validateCardCVC($('.cc-cvc').val(), card_type);
       $(this).parent().parent().removeClass('error');
       if( !card_cvc ) {
         $(this).parent().parent().addClass('error');
       }
         
      check_all_fields();
    })

    
    
    $('#PaylerPostButton').on('click keypress', function(e) {
      if( card_type && card_expired && card_cvc )
      {
        card_num = $('.cc-number').val();
        $('[name="card_number"]').val( card_num.replace(/\s/g, '') );
        $('[name="expired_month"]').val( $('.cc-exp').payment('cardExpiryVal').month );
        $('[name="expired_year"]').val( $('.cc-exp').payment('cardExpiryVal').year % 100 );
        var value = $('#PaylerCardholder').val();
        $('[name="card_holder"]').val( value.toUpperCase() );
        $('form').submit();
        
      }
      if( !card_cvc ) {
        $('#PaylerCode').focus();
        e.preventDefault();
      }
      if( !card_expired ) {
        $('#PaylerExpired').focus();
        e.preventDefault();
      }      
      if( !card_type ) {
        $('#PaylerCardNum').focus();
        e.preventDefault();
      }
      e.preventDefault();
    })
    
    $('form').on('submit', function(){
      $('#PaylerPostButton').removeClass('active').bind('click keypress', function(e){ 
        $(e).preventDefault(); 
      });
    })

    // Вызов проверки при первой загрузке, 
    // оставлен на случай если яваскрипт отключен
    show_error();
    function show_error() {
      // Обработка ошибок
      //
      
      if( error_param ) {
        switch ( error_param ) {
          case 'card_type':
          case 'card_number':
            $('#PaylerCardNum').focus().parent().parent().addClass('error');
          break;
          case 'expired_month':
          case 'expired_year':
            $('#PaylerExpired').focus().parent().parent().addClass('error');
          break;
        }
        $('.payler-error-popup').removeClass( 'hide' ).find('div').text( error_value );
      }
    }


    
    function check_all_fields() {                
      
      if ( card_type && card_cvc && card_expired && card_num )
      {  
        
        $('#PaylerPostButton').addClass( 'active' );
        return true;
      }  
      else
      {
        $('#PaylerPostButton').removeClass( 'active' );
        return false;
      }     
    }
    
    
    $('[data-popup]').on('click', function(e){
      var element = '#'+$(this).attr('data-popup');
      $(element).css('display', 'block');
      
      setTimeout(function(){
      $(element).addClass('visible');
      }, 100);
    })
    
    $('[data-dismiss]').on('click', function(e){
      
      var element = '#'+$(this).attr('data-dismiss');
      $(element).removeClass('visible');
      
      setTimeout(function(){
      $(element).css('display', 'none');
      }, 101);
    })
    
    
  });
