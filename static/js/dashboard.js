(function(a){var c=function(b,c){a.each(c,function(a,c){b=b.replace(RegExp("{{ *"+a+" *}}"),c)});return b};a(function(){var b=a("#zem_rp_statistics_wrap"),h=a("#zem_rp_dashboard_url").val(),f=a("#zem_rp_blog_id").val(),e=a("#zem_rp_zemanta_username").val(),i=a("#zem_rp_auth_key").val();traffic_exchange_enabled=0<a("#zem_rp_show_traffic_exchange_statistics").length;update_interval=req_timeout=null;update_interval_sec=2E3;update_interval_error_sec=3E4;updating=!1;ul=connect_interval=null;stats={};set_update_interval=
function(a){a||(a=update_interval_sec);clearInterval(update_interval);0<a&&(update_interval=setInterval(update_dashboard,a))};display_error=function(g){var j=a("#zem_rp_statistics_wrap");g||j.find(".unavailable").slideDown();set_update_interval(update_interval_error_sec);updating=!1};create_dashboard=function(){ul=a('<ul class="statistics" />');b.find(".unavailable").slideUp();ul.append('<li class="title"><div class="desktop">Desktop</div><div class="mobile">Mobile</div></li>');ul.append(c('<li class="{{class}} stats"><p class="num mobile"></p><p class="num all"></p><h5>{{ title}}<span>{{range}}</span></h5></li>',
{"class":"ctr",title:"click-through rate",range:"last 30 days"}));ul.append(c('<li class="{{class}} stats"><p class="num mobile"></p><p class="num all"></p><h5>{{ title}}<span>{{range}}</span></h5></li>',{"class":"pageviews",title:"page views",range:"last 30 days"}));ul.append(c('<li class="{{class}} stats"><p class="num mobile"></p><p class="num all"></p><h5>{{ title}}<span>{{range}}</span></h5></li>',{"class":"clicks",title:"clicks",range:"last 30 days"}));b.append(ul);traffic_exchange_enabled&&
b.append('<div class="network"><div class="icon"></div><span class="num"></span><h4>Inbound Visitors</h4><div class="description"><p>Number of visitors that came to your site because this plugin promoted your content on other sites.<strong>Wow, a traffic exchange! :)</strong></p></div></div>')};update_dashboard=function(g){updating||(updating=!0,req_timeout=setTimeout(function(){display_error(!g)},2E3),a.getJSON(h+"pageviews/?callback=?",{blog_id:f,auth_key:i},function(a){var d=a.data;clearTimeout(req_timeout);
if(!a||"ok"!==a.status||!a.data)display_error(!g);else{ul||create_dashboard();set_update_interval(a.data.update_interval);stats.mobile_pageviews=Math.max(d.mobile_pageviews,stats.mobile_pageviews||0);stats.mobile_clicks=Math.max(d.mobile_clicks,stats.mobile_clicks||0);a=0<stats.mobile_pageviews&&(100*(stats.mobile_clicks/stats.mobile_pageviews)).toFixed(1)||0;stats.desktop_pageviews=Math.max(d.pageviews-stats.mobile_pageviews,stats.desktop_pageviews||0);stats.desktop_clicks=Math.max(d.clicks-stats.mobile_clicks,
stats.desktop_clicks||0);var c=0<stats.desktop_pageviews&&(100*(stats.desktop_clicks/stats.desktop_pageviews)).toFixed(1)||0;stats.network_in_pageviews=Math.max(d.network_in_pageviews,stats.network_in_pageviews||0);ul.find(".ctr .num.all").html(c+"%");ul.find(".pageviews .num.all").html(stats.desktop_pageviews);ul.find(".clicks .num.all").html(stats.desktop_clicks);ul.find(".ctr .num.mobile").html(a+"%");ul.find(".pageviews .num.mobile").html(stats.mobile_pageviews);ul.find(".clicks .num.mobile").html(stats.mobile_clicks);
b.find(".network .num").html(stats.network_in_pageviews);updating=!1}}))};check_if_connected=function(){jQuery.post(ajaxurl,{action:"zem_rp_is_zemanta_connected"},function(a){"yes"===a&&(clearInterval(connect_interval),window.location.reload())})};a("#zem-rp-login").click(function(){connect_interval=setInterval(check_if_connected,4E3);setTimeout(check_if_connected,300)});!e&&document.location.hash.match(/turn-on-rp/)&&(document.location.hash="",connect_interval=setInterval(check_if_connected,4E3),
setTimeout(check_if_connected,300));e&&f&&(update_dashboard(!0),update_interval=setInterval(update_dashboard,2E3));a(".zem_rp_notification .close").on("click",function(c){a.ajax({url:a(this).attr("href"),data:{noredirect:!0}});a(this).parent().slideUp(function(){a(this).remove()});c.preventDefault()});a("#zem_rp_wrap .collapsible .collapse-handle").on("click",function(c){var b=a(this).closest(".collapsible"),d=b.find(".container"),f=b.hasClass("collapsed"),e=b.attr("block");f?(d.slideDown(),a.post(ajaxurl,
{action:"rp_show_hide_"+e,show:!0})):(d.slideUp(),a.post(ajaxurl,{action:"rp_show_hide_"+e,hide:!0}));b.toggleClass("collapsed");c.preventDefault()})})})(jQuery);
