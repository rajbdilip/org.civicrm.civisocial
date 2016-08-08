<?php
/**
 * Create a social user.
 *
 * @param array $params
 *
 * @return int|bool
 *   Returns ID if exits, FALSE otherwise
 */
function civicrm_api3_civisocial_user_socialUserExists($params) {
  civicrm_api3_verify_mandatory($params, NULL, array('social_user_id', 'oauth_provider'));
  return CRM_Civisocial_BAO_CivisocialUser::socialUserExists($params['social_user_id'], $params['oauth_provider']);
}

/**
 * Create a social user.
 *
 * @param array $params
 *
 * @return array
 *   Array of created values
 */
function civicrm_api3_civisocial_user_create($params) {
  civicrm_api3_verify_mandatory($params, NULL, array('contact_id', 'social_user_id', 'oauth_provider'));
  return _civicrm_api3_basic_create('CRM_Civisocial_BAO_CivisocialUser', $params);
}

/**
 * Creates a contact if doesn't exist and returns it's id.
 *
 * @param array $params
 *
 * @return int
 *   Contact id of created/existing contact
 */
function civicrm_api3_civisocial_user_createContact($params) {
  civicrm_api3_verify_mandatory($params, NULL, array('email, contact_type'));
  return CRM_Civisocial_BAO_CivisocialUser::createContact($params);
}

/**
 * Fetches Facebook event information
 *
 * @param array $params
 */
function civicrm_api3_civisocial_user_getFacebookEventInfo_spec($params) {
  $params['event_id']['api.required'] = 1;
  $params['event_id'] = array(
    'title' => 'Facebook Event ID',
    'description' => 'Facebook Event ID',
    'type' => CRM_Utils_Type::T_STRING,
  );
}

/**
 * Fetches Facebook event information
 *
 * @param array $params
 *
 * @return array
 *   Array of Facebook event information or error messgaes
 */
function civicrm_api3_civisocial_user_getFacebookEventInfo($params) {
  // civicrm_api3_verify_mandatory($params, NULL, array('event_id'));

  $session = CRM_Core_Session::singleton();
  $fbAccessToken = $session->get('facebook_access_token');
  if ($fbAccessToken) {
    $facebook = new CRM_Civisocial_OAuthProvider_Facebook($fbAccessToken);
    if ($facebook->isAuthorized()) {
      $eventId = $params['event_id'];
      $eventInfo = $facebook->get($eventId, array('fields' => 'name,description,place,start_time,end_time'));
      if ($eventInfo) {
        $eventInfo['description'] = nl2br($eventInfo['description']);
        return civicrm_api3_create_success($eventInfo);
      }
      else {
        return civicrm_api3_create_error("The facebook event either doesn't exist or is private.");
      }
    }
  }
  return civicrm_api3_create_error("Not connected to Facebook.");
}

/**
 * Updates status accross different social network
 *
 * @param array $params
 */
function civicrm_api3_civisocial_user_updateStatus_spec($params) {
  $params['post_content']['api.required'] = 1;
  $params['post_content'] = array(
    'title' => 'Status/tweet to update',
    'description' => 'Post/Tweet/Status to be updated across different social networks.',
    'type' => CRM_Utils_Type::T_STRING,
  );
}

/**
 * Makes a post to Facebook and/or Twitter
 *
 * @param array $params
 *
 * @return array
 */
function civicrm_api3_civisocial_user_updateStatus($params) {
  $session = CRM_Core_Session::singleton();
  $response = array();

  if (isset($params['facebook'])) {
    $pageId = $session->get('facebook_page_id');
    $fbAccessToken = $session->get('facebook_page_access_token');
    if ($pageId && $fbAccessToken) {
      // Connected to page
      $facebook = new CRM_Civisocial_OAuthProvider_Facebook($fbAccessToken);
      // Check if token is still valid
      $pageInfo = $facebook->get("{$pageId}?fields=name,picture");
      if ($pageInfo) {
        // Token valid
        $post['message'] = $params['post_content'];
        $result = $facebook->post("{$pageId}/feed", $post);
        $response['facebook']['post_id'] = $result['id'];
      }
      else {
        return civicrm_api3_create_error(ts('Invalid Facebook access token.'));
      }
    }
    else {
      return civicrm_api3_create_error(ts('Not connected to Facebook.'));
    }
  }

  if (isset($params['twitter'])) {
    $twitterId = $session->get('twitter_id');
    $twitterAccessToken = $session->get('twitter_access_token');
    if ($twitterId && $twitterAccessToken) {
      // Connected to Twitter
      $twitter = new CRM_Civisocial_OAuthProvider_Twitter($twitterAccessToken);
      // Check if token is still valid
      if ($twitter->isAuthorized()) {
        $post['status'] = $params['post_content'];
        $result = $twitter->post('statuses/update', $post);
        if ($result && $result['id']) {
          $response['twitter']['tweet_id'] = $result['id'];
        }
      }
      else {
        return civicrm_api3_create_error(ts('Invalid Twitter access token.'));
      }
    }
    else {
      return civicrm_api3_create_error(ts('Not connected to Twitter.'));
    }
  }

  return civicrm_api3_create_success($response);
}


/**
 * Fetches Facebook page Feed
 *
 * @param array $params
 *
 * @return array
 */
function civicrm_api3_civisocial_user_getFacebookPageFeed($params) {
  $response = array();
  $session = CRM_Core_Session::singleton();

  $pageId = $session->get('facebook_page_id');
  $fbAccessToken = $session->get('facebook_page_access_token');
  if ($pageId && $fbAccessToken) {
    // Connected to page
    $facebook = new CRM_Civisocial_OAuthProvider_Facebook($fbAccessToken);

    $feedParams = array();
    $feedParams['fields'] = 'story,message,link,type,from,updated_time';
    $feedParams['limit'] = 5;

    if (isset($params['next'])) {
      $feedParams += $params['next'];
    } else if (isset($params['prev'])) {
      $feedParams += $params['prev'];
    }

    $pageFeed = $facebook->get("{$pageId}/feed", $feedParams);
    if ($pageFeed) {
      $posts = array();
      foreach ($pageFeed['data'] as $feedItem) {
        $post = array();
        $post['message'] = $feedItem['message'];
        $post['link'] = $feedItem['link'];
        $post['from'] = array();
        $post['from']['name'] = $feedItem['from']['name'];
        $post['from']['link'] = "https://www.facebook.com/{$feedItem['from']['id']}";
        $post['from']['picture'] = "https://graph.facebook.com/{$feedItem['from']['id']}/picture";

        $id = explode('_', $feedItem['id']);
        $post['link'] = "https://www.facebook.com/{$id[0]}/posts/{$id[1]}";
        $post['time'] = date('d M, Y H:i:A', strtotime($feedItem['updated_time']));

        array_push($posts, $post);
      }
      $response['data'] = $posts;

      // Get paging params
      $response['next'] = array();

      $pagingNextParams = parsePagingUrl($pageFeed['paging']['next']);
      $response['next']['until'] = $pagingNextParams['until'];
      $response['next']['__paging_token'] = $pagingNextParams['__paging_token'];

      $response['prev'] = array();
      $response['prev']['__previous'] = 1;
      $pagingPrevParams = parsePagingUrl($pageFeed['paging']['previous']);
      $response['prev']['since'] = $pagingPrevParams['since'];
      $response['prev']['__paging_token'] = $pagingPrevParams['__paging_token'];

      return civicrm_api3_create_success($response);
    }
    else {
      return civicrm_api3_create_error(ts('Invalid Facebook access token.'));
    }
  }
  else {
    return civicrm_api3_create_error(ts('Not connected to Facebook.'));
  }
}

/**
 * Fetches Facebook page Feed
 *
 * @param array $params
 *
 * @return array
 */
function civicrm_api3_civisocial_user_getFacebookPageNotifications($params) {
  $session = CRM_Core_Session::singleton();
  $response = array();

  $pageId = $session->get('facebook_page_id');
  $fbAccessToken = $session->get('facebook_page_access_token');
  if ($pageId && $fbAccessToken) {
    // Connected to page
    $facebook = new CRM_Civisocial_OAuthProvider_Facebook($fbAccessToken);

    $notifParams = array();
    $notifParams['fields'] = 'title,from,updated_time,link';
    $notifParams['limit'] = 5;

    if (isset($params['next'])) {
      $notifParams += $params['next'];
    } else if (isset($params['prev'])) {
      $notifParams += $params['prev'];
    }

    $result = $facebook->get("{$pageId}/notifications", $notifParams);
    if ($result) {
      $notifications = array();
      foreach ($result['data'] as $item) {
        $notification = array();

        $id = explode('_', $item['id']);

        $notification['message'] = $item['title'];
        $notification['link'] = "{$item['link']}&ref=notif&notif_id={$id[2]}";
        $notification['from'] = array();
        $notification['from']['name'] = $item['from']['name'];
        $notification['from']['link'] = "https://www.facebook.com/{$item['from']['id']}";
        $notification['from']['picture'] = "https://graph.facebook.com/{$item['from']['id']}/picture";
        $notification['time'] = date('d M, Y H:i:A', strtotime($item['updated_time']));

        array_push($notifications, $notification);
      }

      $response['data'] = $notifications;
      $response['unseen_count'] = $result['summary']['unseen_count'];

      // Get paging params
      $pagingNextParams = parsePagingUrl($result['paging']['next']);
      $response['next'] = array();
      $response['next']['until'] = $pagingNextParams['until'];
      $response['next']['__paging_token'] = $pagingNextParams['__paging_token'];

      $pagingPrevParams = parsePagingUrl($result['paging']['previous']);
      $response['prev'] = array();
      $response['prev']['__previous'] = 1;
      $response['prev']['since'] = $pagingPrevParams['since'];
      $response['prev']['__paging_token'] = $pagingPrevParams['__paging_token'];

      return civicrm_api3_create_success($response);
    }
    else {
      return civicrm_api3_create_error(ts('Invalid Facebook access token.'));
    }
  }
  else {
    return civicrm_api3_create_error(ts('Not connected to Facebook.'));
  }
}

/**
 * Extacts query strings into an array from Facebook's Paging URL
 *
 * @param  $url
 *   Paging URL
 * @return array
 */
function parsePagingUrl($url) {
  $urlParts = explode('?', $url);
  $queryStrings = array();
  parse_str($urlParts[1], $queryStrings);
  return $queryStrings;
}
