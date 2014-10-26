<?php

/*
*
* # 你好！我是来自乌徒帮的否子戈
* # 这个文件是为了实现如下功能的：
* # 当你发布了某条回复时，wordpress会将你的回复记录下来，而当你下次再访问（或刷新）网站的时候，
* # 如果你的这条回复被别人回复了，那么wordpress会提醒你，并让你去回复它。
* # 如果你想获得更多关于功能的细节，请访问我的网站 www.utubon.com
*
*/

// 将发布的回复ID记录在cookie中
add_action('comment_post','comment_reply_notice_add_cookie');
function comment_reply_notice_add_cookie($comment_id){
	//$comment = get_comment($comment_id);
	if(!isset($_COOKIE['comment_reply_notice'])) {
        $comments = serialize(array($comment_id));
        setcookie('comment_reply_notice',$comments,time()+1209600,COOKIEPATH,COOKIE_DOMAIN,false);
    }else{
    	$comments = unserialize($_COOKIE['comment_reply_notice']);
    	if(!is_array($comments))$comments = array();
    	$comments[] = $comment_id;
    	$comments = serialize(array_unique($comments));
    	setcookie('comment_reply_notice',$comments,time()+1209600,COOKIEPATH,COOKIE_DOMAIN,false);
    }
}

// 当点击回复链接，回复完成的时候，把被回复的那条评论从cookie中删除
add_action('init','comment_reply_notice_delete_cookie');
function comment_reply_notice_delete_cookie(){
	if(isset($_GET['comment_id'])) :
		$comment_id = $_GET['comment_id'];
		if(isset($_COOKIE['comment_reply_notice'])){
			$comments = unserialize($_COOKIE['comment_reply_notice']);
			if(in_array($comment_id,$comments)){
				$key = array_search($comment_id,$comments);
				unset($comments[$key]);
				unset($key);
			}
			$comments = serialize($comments);
			setcookie('comment_reply_notice',$comments,time()+1209600,COOKIEPATH,COOKIE_DOMAIN,false);
		}
		wp_redirect(remove_query_arg('comment_id'),301);
		exit;
	endif;
}

// 在你的wordpress中，创建一个提示框，你可以点击提示框中的信息进入回复，但你必须自己修改css和javascript来控制它
function comment_reply_notice_array(){
	if(isset($_COOKIE['comment_reply_notice'])){
		$comments = unserialize($_COOKIE['comment_reply_notice']);
		$return = array();
		foreach($comments as $comment_id){
			$children = get_comments("parent={$comment_id}");
			if(!empty($children))$return[] = $comment_id;
		}
		return $return;
	}else{
		return false;
	}
}

add_action('init','comment_reply_notice_ajax_return');
function comment_reply_notice_ajax_return() {
  if(isset($_GET['comment_reply_notice'])) {
    if(isset($_COOKIE['comment_reply_notice'])) {
      $comments = unserialize($_COOKIE['comment_reply_notice']);
      $return = array();
      foreach($comments as $comment_id){
        $children = get_comments("parent={$comment_id}");
        if(!empty($children)){
          $comment = get_comment($comment_id);
          $return[] = array(
            'link' => urlencode(get_permalink($comment->comment_post_ID)."?comment_id=$comment_id#comment-$comment_id"),
            'content' => urlencode(mb_strimwidth(strip_tags($comment->comment_content),0,50,'...'))
          );
        }
      }
      header("Content-Type: text/html; charset=utf8");
      echo urldecode(json_encode(array(
        'amount' => count($return),
        'list' => $return
      )));
    }
    else {
      echo json_encode(array(
        'amount' => 0
      ));
    }
    exit;
  }
}

add_action('wp_footer','comment_reply_notice_print');
function comment_reply_notice_print(){
	$reply_array = comment_reply_notice_array();
	if($reply_array && !empty($reply_array)){
		echo '<style>#comment-reply-notice-box{position: fixed;_position: absolute;top:10px;right: 10px;background: #ffffff;border:#888 solid 1px;padding: 10px;z-index: 10000;}#comment-reply-notice-box ol.comment-reply-notice-list li{list-style: decimal;margin-left:1.5em;}#comment-reply-notice-box .comment-reply-notice-close{float:right;margin:-0.7em -0.2em 0 0;}</style>';
		echo '<div id="comment-reply-notice-box">';
		echo '<a href="javascript:void(0);" class="comment-reply-notice-close">×</a>';
		echo '<div class="comment-reply-notice-count">您有 '.count($reply_array).' 条评论刚刚被回复！</div>';
		echo '<ol class="comment-reply-notice-list">';
		foreach ($reply_array as $key => $reply) {
			$comment = get_comment($reply);
			$comment_id = $comment->comment_ID;
			$comment_content = mb_strimwidth(strip_tags($comment->comment_content),0,50,'...');
			$post_id = $comment->comment_post_ID;
			echo '<li><a href="'.get_permalink($post_id).'?comment_id='.$comment_id.'#comment-'.$comment_id.'" target="_blank">'.$comment_content.'</a></li>';
		}
		echo '</ol>';
		echo '</div>';
		echo '<script>
    jQuery("#comment-reply-notice-box").animate({top:jQuery(window).height()-jQuery("#comment-reply-notice-box").height()-30,right:jQuery(window).width()-jQuery("#comment-reply-notice-box").width()-30},2000,function(){
			jQuery("#comment-reply-notice-box").animate({top:10,right:10},1000);
		});
    </script>';
	}
  else {
		echo '<style>#comment-reply-notice-box{position: fixed;_position: absolute;top:10px;right: 10px;background: #ffffff;border:#888 solid 1px;padding: 10px;z-index: 10000;display:none;}#comment-reply-notice-box ol.comment-reply-notice-list li{list-style: decimal;margin-left:1.5em;}#comment-reply-notice-box .comment-reply-notice-close{float:right;margin:-0.7em -0.2em 0 0;}</style>';
		echo '<div id="comment-reply-notice-box">';
		echo '<a href="javascript:void(0);" class="comment-reply-notice-close">×</a>';
		echo '<div class="comment-reply-notice-count">您有 <b>'.count($reply_array).'</b> 条评论刚刚被回复！</div>';
		echo '<ol class="comment-reply-notice-list"></ol>';
		echo '</div>';
  }
  echo '<script>setInterval(function(){
      jQuery.get("'.home_url('?comment_reply_notice').'",function(out){
        if(out.amount > 0) {
          jQuery("#comment-reply-notice-box .comment-reply-notice-list").html("");
          var $list = out.list;
          for(var i in $list){
            var $obj = $list[i];
            jQuery("#comment-reply-notice-box .comment-reply-notice-count b").text(out.amount);
            jQuery("#comment-reply-notice-box .comment-reply-notice-list").append("<li><a href=\"" + $obj.link + "\">" + $obj.content + "</a></li>");
            jQuery("#comment-reply-notice-box").show();
            jQuery("#comment-reply-notice-box").animate({
              top:jQuery(window).height()-jQuery("#comment-reply-notice-box").height()-30,
              right:jQuery(window).width()-jQuery("#comment-reply-notice-box").width()-30
            },2000,function(){
              jQuery("#comment-reply-notice-box").animate({top:10,right:10},1000);
            });
          }
        }
      },"json");
    },300000);// 默认是5分钟提醒一次，你可以根据自己的情况进行修改
    jQuery("#comment-reply-notice-box a.comment-reply-notice-close").click(function(){jQuery("#comment-reply-notice-box").remove();});
    </script>';
}
