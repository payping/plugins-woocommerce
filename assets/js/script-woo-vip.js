jQuery(document).ready(function($){            //wrapper
    $(".pp_img_sync").on( 'click', function(){ //event
		$('#pro-up-img').addClass('proccess');
		$('#pro-up-img p').html('درحال ارسال تصویر، لطفا کمی منتظر باشید...');
        var ajaxurl = pp_woo_vip.ajaxurl;
        $.post( ajaxurl, {                     //POST request
	    action: "payping_img_sync_img",        //action
            id: $(this).attr('id'),           //data
            src: $(this).attr('data-value')   //data
        }, function(response){                 //callback
			$('#pro-up-img').removeClass('proccess');
			$('#pro-up-img p').html('');
            if( response.status_code == 200 ){
				alert('با موفقیت تنظیم شد.');
			}else{
				alert('مشکلی در بارگذاری رخ داده است.');
			}
        }, 'json');
    });
	
	
	/* Sync All Item */
	$("button#ItemImgSync").on( 'click', function(){
		$('#pro-up-img').addClass('proccess');
		$('#pro-up-img p').html('درحال ارسال تصویر، لطفا کمی منتظر باشید...');
        var ajaxurl = pp_woo_vip.ajaxurl;
        $.post( ajaxurl, {
	    action: "payping_img_sync_img",
            id: $(this).attr('data-product-id'),
            src: $(this).attr('data-product-src')
        }, function(response){
			$('#pro-up-img').removeClass('proccess');
			$('#pro-up-img p');
		});
	});
	
	/* Sync All Item */
	$("button#ItemSync").on( 'click', function(){
		$('#pro-up-img').addClass('proccess');
		$('#pro-up-img p').html('درحال ارسال تصویر، لطفا کمی منتظر باشید...');
        var ajaxurl = pp_woo_vip.ajaxurl;
        $.post( ajaxurl, {
	    action: "payping_img_sync_img",
            id: $(this).attr('data-product-id'),
            src: $(this).attr('data-product-src')
        }, function(response){
			$('#pro-up-img').removeClass('proccess');
			$('#pro-up-img p');
		});
	});
			  
});