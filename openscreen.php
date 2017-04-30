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
    }

    public static function install()
    {
        global $wpdb;

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}openscreen_ads (id INT AUTO_INCREMENT PRIMARY KEY, ad_img VARCHAR(255) NOT NULL, creator INT NOT NULL, check_nudity INT, check_porn INT, check_racism INT, check_insult INT, check_competition INT, category INT, number_reviewers INT);");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}openscreen_reviews (id INT AUTO_INCREMENT PRIMARY KEY, ad INT NOT NULL, user INT NOT NULL, to_check INT, value INT, why TEXT);");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}openscreen_reviews_of_reviews (id INT AUTO_INCREMENT PRIMARY KEY, id_review INT NOT NULL, user INT NOT NULL, relevant INT);");
    }

    public static function uninstall()
    {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}openscreen_ads;");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}openscreen_reviews;");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}openscreen_reviews_of_reviews;");
    }

    public function result_ad($atts, $content)
    {
       global $wpdb;

       $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}openscreen_ads WHERE creator=".get_current_user_id());
       $uploads = wp_upload_dir();

       foreach ($results as $ads){
         $to_review = false;
         $max_users = $ads->number_reviewers;

         echo "<div style='width: 300px; border: 1px solid gray; text-align: center;clear: both; display: table; margin: auto'>";

         echo '<center><img src="' . esc_url( $uploads['url'] . '/' . $ads->ad_img ) . '" href="#" style="width: 250px; display: block; margin: 10px;"></center>';

         if($ads->check_nudity == 1){
             $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=1");
             echo "Nudity check: ".$counted."/".$max_users;
             echo "<br/>";
         }

         if($ads->check_porn == 1){
           $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=2");
           echo "Pornographic check: ".$counted."/".$max_users;
           echo "<br/>";
         }

         if($ads->check_racism == 1){
           $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=3");
           echo "Racism check: ".$counted."/".$max_users;
           echo "<br/>";
         }

         if($ads->check_insult == 1){
           $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=4");
           echo "Insult check: ".$counted."/".$max_users;
           echo "<br/>";
         }

         if($ads->check_competition == 1){
           $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=5");
           echo "Competition check: ".$counted."/".$max_users;
           echo "<br/>";
         }


         echo "</div>";
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
            $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}openscreen_ads");
            $uploads = wp_upload_dir();
            $counter = 0;

            foreach ($results as $ads){
              $to_review = false;
              $max_users = $ads->number_reviewers;
              $review_array = array();

              if($ads->check_nudity == 1){
                $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=1 AND user=".get_current_user_id());
                if( $counted == 0 ){
                  $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=1");
                  if( $counted < $max_users ){ $to_review = true; $review_nudity = true; $review_array[] = 1;}
                }
              }

              if($ads->check_porn == 1){
                $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=2 AND user=".get_current_user_id());
                if( $counted == 0 ){
                  $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=2");
                  if( $counted < $max_users ){ $to_review = true; $review_porn = true; $review_array[] = 2;}
                }
              }

              if($ads->check_racism == 1){
                $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=3 AND user=".get_current_user_id());
                if( $counted == 0 ){
                  $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=3");
                  if( $counted < $max_users ){ $to_review = true; $review_racism = true; $review_array[] = 3;}
                }
              }

              if($ads->check_insult == 1){
                $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=4 AND user=".get_current_user_id());
                if( $counted == 0 ){
                  $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=4");
                  if( $counted < $max_users ){ $to_review = true; $review_insult = true; $review_array[] = 4;}
                }
              }

              if($ads->check_competition == 1){
                $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=5 AND user=".get_current_user_id());
                if( $counted == 0 ){
                  $counted = $wpdb->get_var("SELECT COUNT(*) as counted FROM {$wpdb->prefix}openscreen_reviews WHERE to_check=5");
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
                        echo "Does this add contain nudity?";
                        break;
                    case 4:
                        echo "Does this add contain nudity?";
                        break;
                    case 5:
                        echo "Does this add contain nudity?";
                        break;
                    default:
                        echo "An error has occured";
                        echo "<br/>".$rand_key;
                  }

                  echo '<center><img src="' . esc_url( $uploads['url'] . '/' . $ads->ad_img ) . '" href="#" style="width: 250px; display: block; margin: 10px;"></center>';
                  echo '<input type="button" value="Yes" style="width: 60px; height: 30px; background-color: green; color: white; font-weight: bold; float: left; margin: 10px; margin-left: 50px;" onclick="javascript:location.href=\'?ad='.$ads->id.'&check='.$check.'&value=1\'" />' ;
                  echo '<input type="button" value="No" style="width: 60px; height: 30px; background-color: red; color: white; font-weight: bold; float: right; margin: 10px; margin-right: 50px;" onclick="javascript:location.href=\'?ad='.$ads->id.'&check='.$check.'&value=0\'" />' ;
                  echo '<span style="clear: left;"></div>';
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
                'check_porn' => (isset($_POST["check-post"])?"1":"0"),
                'check_racism' => (isset($_POST["check-racism"])?"1":"0"),
                'check_insult' => (isset($_POST["check-insult"])?"1":"0"),
                'check_competition' => (isset($_POST["check-competition"])?"1":"0"),
                'category' => $_POST["competition-theme"],
                'number_reviewers' => 10)

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
            echo "<option>Restaurants</option>";
            echo "<option>Hairdressers</option>";
            echo "<option>Clothes retailers</option>";
            echo "<option>IT shops</option>";
            echo "<option>Food retailers</option>";
            echo "<option>Universities</option>";
            echo "<option>Airports</option>";
            echo "</select>";

            echo "<br/><br/><input type='submit' name='submit' value='Submit the ad' />";
            echo "</form>";
        }


    }

}

new openScreen();
?>
