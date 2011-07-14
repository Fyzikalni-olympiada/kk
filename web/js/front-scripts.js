/** Google Analytics */
_ga.create('UA-xxx-x', '.domena.tld');
_gaq.push(['_trackPageview']);

$(function(){

    /** Google +1 */
    var e = document.createElement('script');
    e.src = document.location.protocol + '//apis.google.com/js/plusone.js#lang=cs';
    e.async = true;
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(e, s);

    /** FACEBOOK */
    var fbRoot = document.getElementById('fb-root');
    if (fbRoot) {
        var e = document.createElement('script');
        e.src = document.location.protocol + '//connect.facebook.net/cs_CZ/all.js#xfbml=1';
        e.async = true;
        fbRoot.appendChild(e);
    }


    var ajaxLoadedHandler;
    (ajaxLoadedHandler = function(){

        //prettyPhoto
        $("a[rel^='gallery']").prettyPhoto();

    })();

    $(document).ajaxSuccess(ajaxLoadedHandler);


    /** CAROUSEL */
    if ($('#content div.carousel-item').length > 1) {
        $('#content div.carousel>ul>li>a').each(function(){
            $(this).attr('title', $($(this).attr('href')).find('h2').text());
        });

        var fadingElem = $.browser.msie ? '>img' : '>img';

        var changeItem = function() {
            var $item = $('#content div.carousel-item.active');
            var width = $('div.carousel-content', $item).outerWidth(true);
            
            $('div.carousel-content>div', $item).animate({left: -width}, 200);
            $(fadingElem, $item).fadeTo(200, 0, function(){
                $('#a-' + $item.attr('id')).removeClass('current');
                
                $item.removeClass('active').next()
                    .find('div.carousel-content>div').css({left: width}).end()
                    .find(fadingElem).fadeTo(0,0).end()
                    .addClass('active')
                    .find('img').one('load', function(){
                        $('#content div.carousel-item.active'+fadingElem).fadeTo(200,1);
                        //alert('loaded');
                    }).each(function(){
                        if(this.complete) $(this).trigger("load");
                    });

                $('#a-' + $item.next().attr('id')).addClass('current');

                $('div.carousel-content>div', $item.next()).animate({left: 0}, 200);

                var last = $item.siblings('div.carousel-item:last');
                $item.detach().insertAfter(last);
            });
        };
        var siId = window.setInterval(changeItem, 5000);

        $('#content div.carousel>ul>li>a').live('click', function(e){
            e.preventDefault();
            window.clearInterval(siId);
            
            $(this).closest('ul').find('a').removeClass('current');
            $(this).addClass('current');
            $('#content div.carousel-item.active').removeClass('active');
            
            var toShow = $(this).attr('href');
            var last = $('#content div.carousel>div.carousel-item:last');
            $(toShow)
                .find('div.carousel-content>div').css({left: 0}).end()
                .find(fadingElem).fadeTo(0,1).end()
                .addClass('active')
                .prevAll('div.carousel-item').each(function(){
                    $(this).detach().insertAfter(last);
                });

            siId = window.setInterval(changeItem, 5000);

        });
    }

});