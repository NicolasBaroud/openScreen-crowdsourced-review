<?php
/*
Plugin Name: openScreen
Plugin URI: http://www.openscreen.com
Description: Allows you to use crowdsourcing to validate advertising
Version: 0.1
License: GPL2
*/

class openScreen
{
    public function __construct()
    {
        register_activation_hook(__FILE__, array('openScreen', 'install'));
        register_uninstall_hook(__FILE__, array('openScreen', 'uninstall'));

        add_shortcode('view_ads', array($this, 'view_ads'));
        add_shortcode('post_ad', array($this, 'post_ad'));
        add_shortcode('result_ad', array($this, 'result_ad'));
        add_shortcode('account_ad', array($this, 'account_ad'));
    }

    public static function install()
    {
        global $wpdb;

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}openscreen_ads (id INT AUTO_INCREMENT PRIMARY KEY, ad_img VARCHAR(255) NOT NULL, creator INT NOT NULL, check_nudity INT, check_porn INT, check_racism INT, check_insult INT, check_competition INT, category INT, number_reviewers INT, ad_price DOUBLE);");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}openscreen_reviews (id INT AUTO_INCREMENT PRIMARY KEY, ad INT NOT NULL, user INT NOT NULL, to_check INT, value INT, why TEXT);");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}openscreen_reviews_of_reviews (id INT AUTO_INCREMENT PRIMARY KEY, id_review INT NOT NULL, user INT NOT NULL, relevant INT);");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}openscreen_users (user_id INT PRIMARY KEY, rating INT NOT NULL, money DOUBLE NOT NULL);");
    }

    public static function uninstall()
    {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}openscreen_ads;");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}openscreen_reviews;");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}openscreen_reviews_of_reviews;");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}openscreen_users;");
    }

    public function account_ad($atts, $content)
    {
       global $wpdb;
       $money = $wpdb->get_var("SELECT SUM(ad_price) as money FROM {$wpdb->prefix}openscreen_reviews,{$wpdb->prefix}openscreen_ads  WHERE user=".get_current_user_id()." AND ad=wpn0_openscreen_ads.id");
       echo "<br/>";
       echo "Your current account: ".$money."€<br/>";
       $rating = $wpdb->get_var("SELECT rating FROM {$wpdb->prefix}openscreen_users WHERE user_id=".get_current_user_id());
       echo "Your current rating: ".$rating."/100<br/>";
       $number_ads = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE user=".get_current_user_id());
       echo "You have reviewed: ".$number_ads." features of ad(s)<br/>";
       $number_ads_discarded = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE discarded=1 AND user=".get_current_user_id());
       echo "Discarded reviews: ".$number_ads_discarded." feature(s)<br/>";
       echo "<br/><br/><br/>";
    }

    public function result_ad($atts, $content)
    {
       global $wpdb;
       $uploads = wp_upload_dir();

       if(isset($_GET["validate"])){

         $ad_id = (int)$_GET["validate"];
         $wpdb->update("{$wpdb->prefix}openscreen_ads",
           array( 'validated' => 1),
           array('id'=>$ad_id)
         );

         $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}openscreen_reviews WHERE ad=".$ad_id);
         foreach ($results as $reviews){
           $rating = (double)$wpdb->get_var("SELECT rating FROM {$wpdb->prefix}openscreen_users WHERE user_id=".$reviews->user);
           $rating = $rating + 0.5;
           if($rating > 100) $rating=100;

           $wpdb->update("{$wpdb->prefix}openscreen_users",
             array( 'rating' => $rating),
             array('user_id'=>$reviews->user)
           );
         }

         echo "<center>";
         echo "Your ad has been validated !<br/><br/>";
         echo "<a href='?'>[Go back to previous page]</a>";
         echo "</center>";

       }elseif(isset($_GET["details"])){
         $details = (int)$_GET["details"];

         if(isset($_GET["discard"])){
              $review_id = (int)$_GET["discard"];
              $user = (int)$_GET["user"];
              $rating = (double)$wpdb->get_var("SELECT rating FROM {$wpdb->prefix}openscreen_users WHERE user_id=".$user);
              $rating = $rating - 0.5;
              if($rating < 0) $rating=0;

              $wpdb->update("{$wpdb->prefix}openscreen_users",
                array( 'rating' => $rating),
                array('user_id'=>$user)
              );

              $wpdb->update("{$wpdb->prefix}openscreen_reviews",
                array( 'discarded' => 1),
                array('id'=>$review_id)
              );
         }elseif(isset($_GET["undiscard"])){
              $review_id = (int)$_GET["undiscard"];
              $user = (int)$_GET["user"];
              $rating = (double)$wpdb->get_var("SELECT rating FROM {$wpdb->prefix}openscreen_users WHERE user_id=".$user);
              $rating = $rating + 0.5;
              if($rating > 100) $rating=100;

              $wpdb->update("{$wpdb->prefix}openscreen_users",
                array( 'rating' => $rating),
                array('user_id'=>$user)
              );

              $wpdb->update("{$wpdb->prefix}openscreen_reviews",
                array( 'discarded' => 0),
                array('id'=>$review_id)
              );
         }

         $ad = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}openscreen_ads WHERE creator=".get_current_user_id()." and id=".$details);
         $max_users = $ad->number_reviewers;
         $to_validate = true;

         echo "<center>";
         echo '<center><img src="' . esc_url( $uploads['url'] . '/' . $ad->ad_img ) . '" href="#" style="width: 250px; display: block; margin: 10px;"></center>';

         if($ad->check_nudity == 1){
             $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=1 AND discarded=0 AND ad=".$ad->id);
             echo "Nudity check: ".$counted."/".$max_users;
             echo "<br/>";
             if ($counted!=$max_users) $to_validate = false;
         }

         if($ad->check_porn == 1){
           $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=2 AND discarded=0 AND ad=".$ad->id);
           echo "Pornographic check: ".$counted."/".$max_users;
           echo "<br/>";
           if ($counted!=$max_users) $to_validate = false;
         }

         if($ad->check_racism == 1){
           $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=3 AND discarded=0 AND ad=".$ad->id);
           echo "Racism check: ".$counted."/".$max_users;
           echo "<br/>";
           if ($counted!=$max_users) $to_validate = false;
         }

         if($ad->check_insult == 1){
           $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=4 AND discarded=0 AND ad=".$ad->id);
           echo "Insult check: ".$counted."/".$max_users;
           echo "<br/>";
           if ($counted!=$max_users) $to_validate = false;
         }

         if($ad->check_competition == 1){
           $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=5 AND discarded=0 AND ad=".$ad->id);
           echo "Competition check: ".$counted."/".$max_users;
           echo "<br/>";
           if ($counted!=$max_users) $to_validate = false;
         }

         $money = $wpdb->get_var("SELECT count(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE ad=".$ad->id);
         $money = $money*$ad->ad_price;
         echo "Money spent on the review so far: ".$money."€<br/>";

         echo "<br/>";

         echo "<table width='100%' border='1'>";
         echo "<tr><td class='font-weight: bold;'><b>User</b></td><td class='font-weight: bold;'><b>Rating</b></td><td class='font-weight: bold;'><b>Question</b></td><td class='font-weight: bold;'><b>Answer</b></td><td class='font-weight: bold;'><b>Explaination</b></td><td class='font-weight: bold;'><b>Discarded?</b></td><td class='font-weight: bold;'><b>Bad user review?</b></td></tr>";

         $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}openscreen_reviews WHERE ad=".$ad->id);
         foreach ($results as $reviews){
           switch($reviews->to_check){
             case 1: $check="Nudity?"; break;
             case 2: $check="Pornographic?"; break;
             case 3: $check="Racism?"; break;
             case 4: $check="Insults?"; break;
             case 5: $check="Competition?"; break;
           }

           $username = $wpdb->get_var("SELECT user_nicename FROM {$wpdb->prefix}users WHERE ID=".$reviews->user);
           $rating = (double)$wpdb->get_var("SELECT rating FROM {$wpdb->prefix}openscreen_users WHERE user_id=".$reviews->user);

           $color = ($reviews->discarded?"#ccc":"transparent");
           $link_discard = ($reviews->discarded?"<a href='?details=".$ad->id."&undiscard=".$reviews->id."&user=".$reviews->user."'>[Undiscard review]</a>":"<a href='?details=".$ad->id."&discard=".$reviews->id."&user=".$reviews->user."'>[Discard review]</a>");

           echo "<tr style='background-color: ".$color."'><td>".$username."</td><td>".$rating."/100</td><td>".$check."</td><td>".($reviews->value?"Yes":"No")."</td><td>".$reviews->why."</td><td>".($reviews->discarded?"Yes":"No")."</td><td>".$link_discard."</td></tr>";
         }

         echo "</table>";

         echo "<br/><br/>";

         if($to_validate){
           echo "<b>You ad has been reviewed by all the required workers, do you want to validate it now?</b><br/><br/>";
         }else{
           echo "<b>You ad has not yet been reviewed by all the required workers, do you want to validate it anyway?</b><br/><br/>";
         }

         echo "<input type='button' value='Yes, validate!' onclick='javascript:location.href=\"?validate=".$ad->id."\"'>";

         echo "<br/><br/><a href='?'>[Go back to previous page]</a>";
         echo "</center>";
       }else{
         $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}openscreen_ads WHERE creator=".get_current_user_id()." AND validated<>1");

         foreach ($results as $ads){
           $to_review = false;
           $max_users = $ads->number_reviewers;

           echo "<div style='width: 300px; border: 1px solid gray; text-align: center;clear: both; display: table; margin: auto'>";

           echo '<center><img src="' . esc_url( $uploads['url'] . '/' . $ads->ad_img ) . '" href="#" style="width: 250px; display: block; margin: 10px;"></center>';

           if($ads->check_nudity == 1){
               $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=1 AND discarded=0 AND ad=".$ads->id);
               echo "Nudity check: ".$counted."/".$max_users;
               echo "<br/>";
           }

           if($ads->check_porn == 1){
             $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=2 AND discarded=0 AND ad=".$ads->id);
             echo "Pornographic check: ".$counted."/".$max_users;
             echo "<br/>";
           }

           if($ads->check_racism == 1){
             $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=3 AND discarded=0 AND ad=".$ads->id);
             echo "Racism check: ".$counted."/".$max_users;
             echo "<br/>";
           }

           if($ads->check_insult == 1){
             $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=4 AND discarded=0 AND ad=".$ads->id);
             echo "Insult check: ".$counted."/".$max_users;
             echo "<br/>";
           }

           if($ads->check_competition == 1){
             $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=5 AND discarded=0 AND ad=".$ads->id);
             echo "Competition check: ".$counted."/".$max_users;
             echo "<br/>";
           }

           echo "<a href='?details=".$ads->id."'>[See details and validate]</a>";
           echo "</div><br/>";
         }
       }
    }

    public function view_ads($atts, $content)
    {
        global $wpdb;

        if( isset($_POST["submit"]) ){
          $wpdb->insert(
            "{$wpdb->prefix}openscreen_reviews",
            array(
              'ad' => (int)$_POST["ad"],
              'user' => get_current_user_id(),
              'to_check' => (int)$_POST["check"],
              'value' => (int)$_POST["val"],
              'why' => esc_sql($_POST["explain"]))
            );


            $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_users WHERE user_id=".get_current_user_id());
            $money = $wpdb->get_var("SELECT ad_price FROM {$wpdb->prefix}openscreen_ads WHERE id=".(int)$_POST["ad"]);

            if( $counted == 0 ){
              $wpdb->insert(
                "{$wpdb->prefix}openscreen_users",
                array(
                  'user_id' => get_current_user_id(),
                  'rating' => 50,
                  'money' => $money)
                );
            }else{
                $wpdb->update("{$wpdb->prefix}openscreen_users",
                  array( 'money' => $money),
                  array('user_id'=>get_current_user_id())
                );
            }

            echo "<center>Thank you for your contribution !</center>";

        }elseif( isset($_GET["ad"]) ){
          echo "<form method='post' action=''>";
          echo "<center>";
          echo "Can you briefly explain why the advertising doesn't comply with the requirement?<br/><br/>";
          echo "<textarea rows=10 cols=60 name='explain'></textarea><br/>";
          echo "<input type='hidden' name='ad' value='".$_GET["ad"]."'>";
          echo "<input type='hidden' name='check' value='".$_GET["check"]."'>";
          echo "<input type='hidden' name='val' value='".$_GET["value"]."'>";
          echo "<input type='submit' name='submit' value='Submit the answer'>";
          echo "</center>";
          echo "</form>";
        }else{
            // Need to add a constraint not to show an add posted by the user
            $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}openscreen_ads WHERE validated<>1");
            $uploads = wp_upload_dir();
            $counter = 0;

            foreach ($results as $ads){
              $to_review = false;
              $max_users = $ads->number_reviewers;
              $review_array = array();

              if($ads->check_nudity == 1){
                $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=1 AND ad=".$ads->id." AND user=".get_current_user_id());
                if( $counted == 0 ){
                  $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=1 AND discarded=0 AND ad=".$ads->id);
                  if( $counted < $max_users ){ $to_review = true; $review_nudity = true; $review_array[] = 1;}
                }
              }

              if($ads->check_porn == 1){
                $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=2 AND ad=".$ads->id." AND user=".get_current_user_id());
                if( $counted == 0 ){
                  $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=2 AND discarded=0 AND ad=".$ads->id);
                  if( $counted < $max_users ){ $to_review = true; $review_porn = true; $review_array[] = 2;}
                }
              }

              if($ads->check_racism == 1){
                $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=3 AND ad=".$ads->id." AND user=".get_current_user_id());
                if( $counted == 0 ){
                  $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=3 AND discarded=0 AND ad=".$ads->id);
                  if( $counted < $max_users ){ $to_review = true; $review_racism = true; $review_array[] = 3;}
                }
              }

              if($ads->check_insult == 1){
                $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=4 AND ad=".$ads->id." AND user=".get_current_user_id());
                if( $counted == 0 ){
                  $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=4 AND discarded=0 AND ad=".$ads->id);
                  if( $counted < $max_users ){ $to_review = true; $review_insult = true; $review_array[] = 4;}
                }
              }

              if($ads->check_competition == 1){
                $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=5 AND ad=".$ads->id." AND user=".get_current_user_id());
                if( $counted == 0 ){
                  $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=5 AND discarded=0 AND ad=".$ads->id);
                  if( $counted < $max_users ){ $to_review = true; $review_competition = true; $review_array[] = 5;}
                }
              }

              if($to_review){
                  $counter++;

                  $rand_key = array_rand($review_array, 1);

                  echo "<div style='width: 300px; border: 1px solid gray; text-align: center;clear: both; display: table; margin: auto'>";
                  $check=$review_array[$rand_key];
                  switch($check){
                    case 1:
                        echo "Does this add contain nudity?";
                        break;
                    case 2:
                        echo "Does this add contain pornographic content?";
                        break;
                    case 3:
                        echo "Does this add contain racism?";
                        break;
                    case 4:
                        echo "Does this add contain insults?";
                        break;
                    case 5:
                        switch($ads->category){
                            case 1:
                                  $category="a restaurant";
                                  break;
                            case 2:
                                  $category="a hairdresser";
                                  break;
                            case 3:
                                  $category="a clothing retailer";
                                  break;
                            case 4:
                                  $category="a IT shop";
                                  break;
                            case 5:
                                  $category="a food retailer";
                                  break;
                            case 6:
                                  $category="a university";
                                  break;
                            case 7:
                                  $category="an airport";
                                  break;
                        }
                        echo "Is this an ad for ".$category."?";
                        break;
                    default:
                        echo "An error has occured";
                        echo "<br/>".$rand_key;
                  }

                  echo '<br/>You can earn: '.$ads->ad_price.'€<br/>';
                  echo '<center><img src="' . esc_url( $uploads['url'] . '/' . $ads->ad_img ) . '" href="#" style="width: 250px; display: block; margin: 10px;"></center>';
                  echo '<input type="button" value="Yes" style="width: 60px; height: 30px; background-color: green; color: white; font-weight: bold; float: left; margin: 10px; margin-left: 50px;" onclick="javascript:location.href=\'?ad='.$ads->id.'&check='.$check.'&value=1\'" />' ;
                  echo '<input type="button" value="No" style="width: 60px; height: 30px; background-color: red; color: white; font-weight: bold; float: right; margin: 10px; margin-right: 50px;" onclick="javascript:location.href=\'?ad='.$ads->id.'&check='.$check.'&value=0\'" />' ;
                  echo '<span style="clear: left;"></div><br/>';
              }

            }

            if($counter==0){
              echo "<center>Sorry, you have already reviewed all the ads !</center>";
            }
        }
    }

    public function post_ad($atts, $content)
    {
        global $wpdb;

        if ( isset( $_POST["submit"] ) ) {
          if ( isset( $_FILES['upload-ad'] ) ) {
            $uploads = wp_upload_dir();
            $file = wp_upload_bits( $_FILES['upload-ad']['name'], null, @file_get_contents( $_FILES['upload-ad']['tmp_name'] ) );

            $wpdb->insert(
            	"{$wpdb->prefix}openscreen_ads",
            	array(
            		'ad_img' => $_FILES['upload-ad']['name'],
            		'creator' => get_current_user_id(),
                'check_nudity' => (isset($_POST["check-nudity"])?"1":"0"),
                'check_porn' => (isset($_POST["check-porn"])?"1":"0"),
                'check_racism' => (isset($_POST["check-racism"])?"1":"0"),
                'check_insult' => (isset($_POST["check-insult"])?"1":"0"),
                'check_competition' => (isset($_POST["check-competition"])?"1":"0"),
                'category' => (int)$_POST["competition-theme"],
                'number_reviewers' => (int)$_POST["number-user"],
                'ad_price' => (double)$_POST["price-user"]
                )

            	);

            echo "Your ad has been added to the review queue and will be processed as soon as possible";
          }else{
            echo "An error has occured, please try again !";
          }

        }else{

            echo "<form method='post' action='' enctype='multipart/form-data'><br/>";
            echo "<label for='upload-ad'>Select the ad to review on your computer :&nbsp;&nbsp;</label>";
            echo "<input type='file' id='upload-ad' name='upload-ad' value='' /><br/><br/>";

            echo "<label for='check-nudity'>Check for nudity :&nbsp;&nbsp;</label>";
            echo "<input type='checkbox' id='check-nudity' name='check-nudity' /><br/>";

            echo "<label for='check-porn'>Check for pornographic content :&nbsp;&nbsp;</label>";
            echo "<input type='checkbox' id='check-porn' name='check-porn' /><br/>";

            echo "<label for='check-racism'>Check for racism :&nbsp;&nbsp;</label>";
            echo "<input type='checkbox' id='check-racism' name='check-racism' /><br/>";

            echo "<label for='check-insult'>Check for insults :&nbsp;&nbsp;</label>";
            echo "<input type='checkbox' id='check-insult' name='check-insult' /><br/>";

            echo "<label for='check-competition'>Check for competition :&nbsp;&nbsp;</label>";
            echo "<input type='checkbox' id='check-competition' name='check-competition' /><br/>";

            echo "<label for='competition-theme'>This ad can't be displayed in :&nbsp;&nbsp;</label>";
            echo "<select id='competition-theme' name='competition-theme' />";
            echo "<option value='1'>Restaurants</option>";
            echo "<option value='2'>Hairdressers</option>";
            echo "<option value='3'>Clothes retailers</option>";
            echo "<option value='4'>IT shops</option>";
            echo "<option value='5'>Food retailers</option>";
            echo "<option value='6'>Universities</option>";
            echo "<option value='7'>Airports</option>";
            echo "</select>";

            echo "<label for='number-user'>Number of user to review each feature :&nbsp;&nbsp;</label>";
            echo "<select id='number-user' name='number-user' />";

            for($i=0; $i<100; $i++)  echo "<option>".$i."</option>";

            echo "</select>";

            echo "<label for='price-user'>Price per user :&nbsp;&nbsp;</label>";
            echo "<input type='input' id='price-user' name='price-user' /><br/>";

            echo "<br/><br/><input type='submit' name='submit' value='Submit the ad' />";
            echo "</form>";
        }


    }

}

new openScreen();
?>
