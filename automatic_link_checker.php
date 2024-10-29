<?php
/*
Plugin Name: Automatic link checker
Description:  This plugin scans your blog posts for any links and removes any links which are no longer indexed in Google, or where the site is down.
Version: 1.1
Author: <a href="http://LinkAuthority.com">LinkAuthority.com</a> and <a href="http://ArticleRanks.com">ArticleRanks.com</a> Team

*/

	
	include_once "newbrowser.php";

	register_activation_hook( __FILE__, 'link_checker_activate' );

	function link_checker_activate(){
		global $wpdb;
		
		// cleaning
		//$wpdb->query("DROP TABLE `{$wpdb->prefix}linkchecker`");
		
		
		$wpdb->query("CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}linkchecker` (
					  `id` int(11) NOT NULL AUTO_INCREMENT,
					  `full_link` text NOT NULL,
					  `domain` text NOT NULL,
					  `reason` text NOT NULL,
					  `date` bigint(20) NOT NULL,
					  `post_id` int(11) NOT NULL,
					  `inside_id` int(11) NOT NULL,
					  PRIMARY KEY (`id`)
					) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");
					
		update_option('lc_period', 10);
		update_option('lc_postsquantity', 3);
		update_option('lc_period_between', 10);
		update_option('lc_post_age', 60);
		update_option('lc_period_delete', 60);
	//	update_option('lc_noresp', "leave");
		update_option('lc_posts_stopped',0); 
		
		$lc_period_sec = 10*24*60*60;
		$lc_last_full_check = time() - $lc_period_sec + 15; // plugin will start working after 15 seconds after activation
		
		$lc_period_between = 10 * 60;
		$lc_last_chack = time() - $lc_period_between + 15; 
		
		update_option('lc_last_full_check',	$lc_last_full_check);
		update_option('lc_last_check',$lc_last_chack);


	}

	
	add_action ('shutdown','link_checker_perform');
	function link_checker_perform(){
	
		$last_full_check = get_option('lc_last_full_check');
		$period_big = get_option('lc_period')*24*60*60;
		$last_check = get_option('lc_last_check');
		$period = get_option('lc_period_between') * 60;
		$now = time();
	
		//check if is OK to perform check now
		if ((($now - $last_full_check) > $period_big)  && (($now - $last_check) > $period)){
		
			 link_checker_check();
		
		
		}
	
	}
	

	add_action('admin_menu', 'link_checker_plugin');

	function link_checker_plugin() {

		//create new top-level menu
		add_menu_page('Automatic link checker', 'Automatic link checker', 'administrator', 'link_checker', 'link_checker_settings');

	}


		
function site_response($url){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		
			
		curl_setopt ($ch,CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 100); //follow up to 10 redirections - avoids loops
		$data = curl_exec($ch);
		curl_close($ch);
		

		
		
		if ($data != ''){
	
			preg_match_all("/HTTP\/1\.[1|0]\s(\d{3})/",$data,$matches);
			$code = end($matches[1]);
									
			if  (count($matches[1])>1){
			
				
				if ($code == 200){
				
					preg_match_all("/Location:\s(.+)\s/",$data,$url);
					
					$url = end($url[1]);
				
					
					
					return "{$matches[1][0]}|||{$url}";
					
				}else{
				
					return "{$code}|||bad";
				
				}
			
			}else{
			
				if ($code == 200){
					
					return "200|||good";
					
				}elseif($code == 405 || $code == 403){
				
					return "200|||good";
				
				
				}else{
				
					return "{$code}|||bad";
				
				}
			
			}
					
		}else{
		

				return "404|||bad";
			

			
		
		}
	
	}
	
	function google_index($url){
		
		$parser = new lc_http_parse;
		
		for ($i=0; $i<3;$i++){
		
			$file = $parser->get("http://www.google.com/search?&q=site:$url&hl=en");
			if ($file != ''){
			
				$i=3;
			
			}
		
		
		}
		
		
		if ($file == ''){
			
	//		if(get_option('lc_noresp') == "leave"){
			
				return true;
				
		//	}else{
				
	//			return false;
			
	//		}
		
		}
		
		unset ($parser);

		if(preg_match("!About (.*?) results!si",$file,$ok)){
		
			//$result=$ok[1];
			//$result=str_replace(",", "", $result);
			$result=true;
		}elseif(preg_match("!([0-9]+) result[s]*!si",$file,$ok)){
			//$result=$ok[1];
			$result=true;
		}else{
			if(preg_match("!No results found!si",$file)) $result="0";
			//else $result="0";
			$result=false;
		}
		return $result;
	}

	
	function lc_get_links($content, $links){
		$thisurl = get_bloginfo('url');
		$thisurl = str_replace ("https://",'', $thisurl);
		$thisurl = str_replace ("http://",'', $thisurl);
		$thisurl = explode('/',$thisurl);
		$thisurl = $thisurl[0];
		
		
		
		
		$quot = "'";
		preg_match_all('|<a.*href=["'.$quot.']([^"]*)["'.$quot.'].*>*.</a>|isU', $content, $lnk);

		$i=0;
		foreach ($lnk[1] as $link){
				
		 
			$link = str_replace ("https://",'', $link);
			$link = str_replace ("http://",'', $link);
			$domain = explode('/',$link);
			
			if (strpos($domain[0], ".") > 1 && $domain[0] != $thisurl){
			
				$links[] = array('full_link' => $lnk[0][$i], 'domain' => $domain[0]);
			
			}
		 
			$i++;
		}
	
	
	}
	

	
	// check! check! check! check! check! check! check! check! check! check! check! check! check! check! check! check! 
	// check! check! check! check! check! check! check! check! check! check! check! check! check! check! check! check! 
	// check! check! check! check! check! check! check! check! check! check! check! check! check! check! check! check! 
	
	
	function link_checker_check(){
		global $wpdb;
		update_option('lc_last_check', time());
		$posts_quantity = get_option('lc_postsquantity');
		$post_stopped =  get_option('lc_posts_stopped');
		$seconds_wait =  get_option('lc_period_delete') * 24 * 60 * 60;
		$seconds_wait =  get_option('lc_period_delete') * 24 * 60 * 60;
		
		//`post_date` < FROM_UNIXTIME ({$posts_age})
		
		$posts_age = time() - (get_option('lc_post_age') * 24 * 60 * 60);
		$posts_age = date('Y-m-d G:i:s', $posts_age);					
		$posts = $wpdb->get_results("SELECT `ID`, `post_content` FROM `{$wpdb->prefix}posts` WHERE `post_status` = 'publish' AND `post_date` < '{$posts_age}' AND `ID` > {$post_stopped} ORDER BY `ID` ASC limit 0,{$posts_quantity}",ARRAY_A);
	
		if (count($posts) < $posts_quantity){
		
			update_option('lc_posts_stopped',0); 
			update_option('lc_last_full_check', time());
			
		}else{
		
			update_option('lc_posts_stopped',$posts[count($posts) - 1]['ID']); 
		
		};
		
		
		

		foreach ($posts as $post){
		
			$links = array ();
			$bad_links = array ();
			$inside_id = $wpdb->get_var("SELECT MAX(inside_id) FROM `{$wpdb->prefix}linkchecker` WHERE `post_id` = {$post['ID']};");
			if ($inside_id == ''){$inside_id = 0;}
			
			$post_content = $post['post_content'];
			
			lc_get_links($post_content, &$links);
			
			foreach ($links as $link){
			
				$response = site_response($link['domain']);
				$response = explode('|||', $response);
				
				if ($response[0] == 200){
				
					$response = true;
					
					if(google_index($link['domain'])){
					
						$gindex = true;
						
					}else{
					
						$gindex = false;
						$reason = "google";
						
					}
				
				}elseif($response[0] == 301 || $response[0] == 302){
				
					$response = true;
				
				
					if(google_index($response[1])){
					
						$gindex = true;
						
					}else{
					
						$gindex = false;
						$reason = "google";
						
					}
				
				
				
				}else{
					
					$reason = $response[0];
					$response = false;
					
				
				}
				
				
				
				
			
			
				if (!$response || !$gindex){
				// if check failed - replace link and add info to DB
				
					
					preg_match('|<a.*>(.*)</a>|isU', $link['full_link'], $anchor); //anchor[1]
					$inside_id ++;
					
					//write info to DB
					$wpdb->insert("{$wpdb->prefix}linkchecker", array('full_link'=>$link['full_link'], 'domain'=>$link['domain'], 'reason'=>$reason, 'date'=>time(), 'post_id'=>$post['ID'], 'inside_id'=>$inside_id)); 
					
					//change post text
					$post_content = str_replace($link['full_link'],"<!--lid={$inside_id}-->".$anchor[1]."<!--e-->", $post_content);
					//$content = str_replace($link['full_link'],$anchor[1], $post['post_content']);
					
					 $my_post = array();
					 $my_post['ID'] = $post['ID'];
					 $my_post['post_content'] = $post_content;

					// Update the post into the database
					  wp_update_post( $my_post );
				
								
				}
			
			
			}
		
			unset ($links);
			
			// check bad links
			// check bad links
			// check bad links
			// check bad links
			
			$bad_links = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}linkchecker WHERE `post_id` = '{$post['ID']}';", ARRAY_A);
			foreach ($bad_links as $bad_link){
			
				$url = "http://".$bad_link['domain'];
				$response = site_response($url);
				$response = explode('|||', $response);			
			
				if ($response[0] == 200){
					
						$response = true;
						
						if(google_index($url)){
						
							$gindex = true;
							
						}else{
						
							$gindex = false;
							
							
						}
					
					}elseif($response[0] == 301 || $response[0] == 302){
					
						$response = true;
					
					
						if(google_index($response[1])){
						
							$gindex = true;
							
						}else{
						
							$gindex = false;
														
						}
					
					
					
					}else{
					
						$response = false;
											
					}
			
			
			
				
				if ($response && $gindex){
				// if everything is OK - turn link back
					// Update the post into the database
					$post_content = preg_replace ("|<!--lid={$bad_link['inside_id']}-->.*<!--e-->|isU", $bad_link['full_link'],$post_content);
					$my_post = array();
					$my_post['ID'] = $post['ID'];
					$my_post['post_content'] = $post_content;
					wp_update_post( $my_post );
					
					//delete bad link
					$wpdb->query("DELETE FROM `{$wpdb->prefix}linkchecker` WHERE `id` = {$bad_link['id']} LIMIT 1");
				
				}else{
					//check for how many days allready inactive. If too long - delete it
					if ((time() - $bad_link['date']) > $seconds_wait){

						$post_content = preg_replace ("|<!--lid={$bad_link['inside_id']}-->(.*)<!--e-->|isU", "$1",$post_content);
						$my_post = array();
						$my_post['ID'] = $post['ID'];
						$my_post['post_content'] = $post_content;
						wp_update_post( $my_post );
						$wpdb->query("DELETE FROM `{$wpdb->prefix}linkchecker` WHERE `id` = {$bad_link['id']} LIMIT 1");
					
					}
				
				
				}
			
			}
			
			
			
		}
		
		
	
	}
	function test_queries($type, $query){
	
		global $wpdb;
	
		$posts = $wpdb->get_results($query);
		
			
			
		$ids = array();
		foreach ($posts as $post){
		
			$ids[] = "<a href='".get_permalink($post->ID)."'>{$post->ID}</a>";
		
		}
		$ids = implode(', ', $ids);
	
		echo "next to be checked - {$ids} <br>";
	
	}
	
	function link_checker_settings() {
		
		global $wpdb;
		
		if (isset ($_POST['option_change'])){

			update_option('lc_period', $_POST['lc_period']);
			update_option('lc_postsquantity', $_POST['lc_postsquantity']);
			update_option('lc_period_between', $_POST['lc_period_between']);
			update_option('lc_post_age', $_POST['lc_post_age']);
			update_option('lc_period_delete', $_POST['lc_period_delete']);
		//	update_option('lc_noresp', $_POST['lc_noresp']);
			$update = true;
			
		}

		if ($_POST['check'] == "ON"){

			
			
			link_checker_check();

			

		}
		
		if(isset($_GET['rid'])){
			
			if ($_GET['rid'] == "all"){
			
				
			
				$bad_links = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}linkchecker;", ARRAY_A);
				
				foreach ($bad_links as $bad_link){
					$post_id = $bad_link['post_id'];
					$post_content = $wpdb->get_var("SELECT `post_content` FROM {$wpdb->prefix}posts WHERE `ID` = '{$post_id}';");
					
					
					$content = preg_replace ("|<!--lid={$bad_link['inside_id']}-->.*<!--e-->|isU", $bad_link['full_link'],$post_content);
					$my_post = array();
					$my_post['ID'] = $post_id;
					$my_post['post_content'] = $content;
					wp_update_post( $my_post );
					
					
					$wpdb->query("DELETE FROM `{$wpdb->prefix}linkchecker` WHERE `id` = {$bad_link['id']} LIMIT 1");
					
					echo "<script> top.location.href='/wp-admin/admin.php?page=link_checker'</script>";
				}
			}else{
		
				$bad_link = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}linkchecker WHERE `id` = '{$_GET['rid']}';", ARRAY_A);
				
				$post_id = $bad_link['post_id'];
				$post_content = $wpdb->get_var("SELECT `post_content` FROM {$wpdb->prefix}posts WHERE `ID` = '{$post_id}';");
				
				
				$content = preg_replace ("|<!--lid={$bad_link['inside_id']}-->.*<!--e-->|isU", $bad_link['full_link'],$post_content);
				$my_post = array();
				$my_post['ID'] = $post_id;
				$my_post['post_content'] = $content;
				wp_update_post( $my_post );
				
				
				$wpdb->query("DELETE FROM `{$wpdb->prefix}linkchecker` WHERE `id` = {$_GET['rid']} LIMIT 1");
		
			}
		}

	?>
	<div class="wrap">
	<h2>Automatic link checker</h2>
		<?php if($update){?>
			<div class="updated"><p><strong>Settings were saved</strong></p></div>
		<?php }?>
	<a href='http://www.linkauthority.com'><img src='http://www.linkauthority.com/i/b/linkauthority_336x280.gif' border=0></a>
	<a href="http://www.articleranks.com/" ><img src="http://www.articleranks.com/images/banner/it1/articleranks_336x280.gif" alt="Article Marketing" width="336" height="280" border="none" ></a>
	<br>
	<form method="post"  >

	<table>
	<tr><td>Launch Automatic Check every: </td><td><input name="lc_period" type="text" value="<?php if (get_option('lc_period') != ''){echo get_option('lc_period');}?>"> days</td></tr>
	<tr><td>Quantity of posts to check at one time</td><td><input name="lc_postsquantity" type="text" value="<?php if (get_option('lc_postsquantity') != ''){echo get_option('lc_postsquantity');}?>"> </td></tr>
	<tr><td>Minimum time between checks</td><td><input name="lc_period_between" type="text" value="<?php if (get_option('lc_period_between') != ''){echo get_option('lc_period_between');}?>"> minutes</td></tr>
	<tr><td>Check posts older than</td><td><input name="lc_post_age" type="text" value="<?php if (get_option('lc_post_age') != ''){echo get_option('lc_post_age');}?>"> days</td></tr>
	<tr><td>Delete links if not active after</td><td><input name="lc_period_delete" type="text" value="<?php if (get_option('lc_period_delete') != ''){echo get_option('lc_period_delete');}?>"> days</td></tr>
	<?php
		/*
		<tr><td>If there is no response from google</td>
				<td>
				<input name="lc_noresp" type="radio" value="leave" <?php if (get_option('lc_noresp') == 'leave'){echo "checked";}?>> Leave link<br>
				<input name="lc_noresp" type="radio" value="deactivate" <?php if (get_option('lc_noresp') == 'deactivate'){echo "checked";}?>>	Deactivate link
				</td></tr>

		*/


	?>
		</table>
	   <input name="option_change" type="hidden" value="">
	  

		<p class="submit">
		<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
		</p>

	</form>
	<hr>
<?php /*
	<form method="post"  >

	<input name="check" type="hidden" value="ON"> <?php //echo "<br>".get_option('lc_posts_stopped'); ?><br>

	<?php 

	$time = get_option('lc_last_check');
	$post_stopped = get_option('lc_posts_stopped');
	$posts_age = time() - (get_option('lc_post_age') * 24 * 60 * 60);
	$posts_age = date('Y-m-d G:i:s', $posts_age);
	
	$query = "SELECT `ID`, `post_content` FROM `{$wpdb->prefix}posts` WHERE `post_status` = 'publish' AND `post_date` < '{$posts_age}' AND `ID` > {$post_stopped} ORDER BY `ID` ASC";
	test_queries('next to be checked', $query);	
	
	

	echo date('h:i:s d-m-Y', get_option('lc_last_full_check'))." - last global check<br>";
	echo date('h:i:s d-m-Y', $time)." - last check ";
	
	//echo "<br>".get_option('lc_posts_stopped');

	 ?>

		
		<br>
		<input type="submit" class="button-primary" value="check next <?php echo get_option('lc_postsquantity');?> posts" />
	</form>
	<hr>
	<br>
*/?>	
	
	

	
	<table class="widefat compact ">
		<thead>
			<tr>
			<th scope="col">Deactivated links</th>
			<th scope="col">Post</th>
			<th scope="col">Reason</th>
			<th scope="col"><a href="admin.php?page=link_checker&rid=all">restore ALL</a></th>
			</tr>
		
		</thead>
		<tbody>
	
	
	<?php
	
		$links = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}linkchecker");
		
		foreach($links as $link){
		
		if ($link->reason == "google"){
		
			$reason = "Not Found in Google";
		
		}else{
		
			$reason = "error code: {$link->reason}";
		
		}
		
		echo "<tr><td>{$link->domain}</td><td><a href='".get_permalink($link->post_id)."'>".get_the_title($link->post_id)."</a></td><td>{$reason}</td><td><a href=\"admin.php?page=link_checker&rid={$link->id}\">restore</a></td></tr>";
		
		
		}
	
	
	?>
	
	
	</tbody>
	</table>

	</div>
	<?php

	}

	?>