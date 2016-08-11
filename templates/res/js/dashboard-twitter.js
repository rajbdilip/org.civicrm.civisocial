CRM.$(function($) {
  var maxId = 0, sinceId = 0, followesrPrev = 0, followersNext = 0;

  $('#tweets-next').click(function() {
    if (maxId === 0) {
      getTweets();
    }
    else {
      getTweets({'max_id' : maxId});
    }
  });

  $('#tweets-prev').click(function() {
    if (sinceId === 0) {
      getTweets();
    }
    else {
      getTweets({'since_id' : sinceId});
    }
  });

  $('#followers-prev').click(function() {
    getFollowers({'cursor' : followersPrev});
  });

  $('#followers-next').click(function() {
    getFollowers({'cursor' : followersNext});
  });

  function getTweets(postData) {
    postData = postData || {};
    showLoader($('#tweets'));

  	CRM.api3('CivisocialUser', 'gettwitterfeed', postData).done(function(result) {
      if (!result.is_error) {
        processAjaxResult('tweets', result.values.data, postData);

        var tweets = result.values.data;
        for (var i = 0; i < tweets.length; i++) {
          var tweet = tweets[i];
          var quotedStatusHtml = '';

          if (typeof tweet.quoted_status != 'undefined') {
            var quotedStatus = tweet.quoted_status;
            quotedStatusHtml = '' +
               '<div class="activity quoted-status">' +
                '<div class="avatar">' +
                  '<a target="_blank" href="http://twitter.com/' + quotedStatus.user.screen_name + '"><img src="' + quotedStatus.user.image + '"></a>' +
                '</div>' +
                '<div class="message">' +
                  '<span class="posted-by"><a href="http://twitter.com/' + quotedStatus.user.screen_name + '">' + quotedStatus.user.name + '</a></span>' +
                  '<span class="activity-status">' + quotedStatus.time + '</span>' +
                  quotedStatus.text + 
                '</div>' +
              '</div>'; 
          }

          var retweeted = '';
          if (typeof tweet.retweeted != 'undefined') {
            retweeted = '<span class="retweeted">Retweeted</span>';
          }

          var postHtml = '' +
            '<div class="activity">' +
              retweeted +
              '<div class="avatar">' +
                '<a target="_blank" href="http://twitter.com/' + tweet.user.screen_name + '"><img src="' + tweet.user.image + '"></a>' +
              '</div>' +
              '<div class="message">' +
                '<span class="posted-by"><a href="http://twitter.com/' + tweet.user.screen_name + '">' + tweet.user.name + '</a></span>' +
                tweet.text + 
                quotedStatusHtml +
                '<span class="activity-status">' + tweet.time + '</span>' +
                '<ul class="actions">' +
                  '<li><a target="_blank" href="http://twitter.com/intent/tweet?in_reply_to=' + tweet.id +'">Reply</a></li>' +
                  '<li><a target="_blank" href="http://twitter.com/intent/retweet?tweet_id=' + tweet.id +'">Retweet</a></li>' +
                  '<li><a target="_blank" href="http://twitter.com/intent/like?tweet_id=' + tweet.id +'">Like</a></li>' +
                '</ul>' +
              '</div>' +
            '</div>';
          
          $('#tweets').append(postHtml);
        }

        maxId = result.values.max_id;
        sinceId = result.values.since_id;
        hideLoader($('#tweets'));
      }
    });
  }

  function getFollowers(postData) {
    postData = postData || {};
    showLoader($('#followers'));

    CRM.api3('CivisocialUser', 'gettwitterfollowers', postData).done(function(result) {
      if (!result.is_error) {
        // Show hide navigation buttons
        followersNext = result.values.next;
        followersPrev = result.values.previous;

        var nextBtn = $('#followers-next').parent();
        var prevBtn = $('#followers-prev').parent();

        if (followersNext === 0) {
          $(nextBtn).hide();
        }
        else if (followersPrev === 0) {
          $(prevBtn).hide();
        }
        else {
          $(nextBtn.show());
          $(prevBtn.show());

        }

        var followers = result.values.followers;
        for (var i = 0; i < followers.length; i++) {
          var follower = followers[i];

          var html = '' +
            '<div class="activity follower">' +
              '<div class="avatar">' +
                '<a target="_blank" href="https://twitter.com/' + follower.screen_name + '"><img src="' + follower.image + '"></a>' +
              '</div>' +
              '<div class="info">' +
                '<div class="name"><a target="_blank" href="http://twitter.com/' + follower.screen_name + '">' + follower.name + '</a></div>' +
                '<ul class="actions">' +
                  '<li><a target="_blank" href="https://twitter.com/intent/tweet?text=%40' + follower.screen_name + '+">Tweet</a></li>' +
                '</ul>' +
              '</div>' +
            '</div>';
        
          $('#followers').append(html);
        }

        followersNext = result.values.next;
        followersPrev = result.values.previous;

        hideLoader($('#followers'));
      }
    });
  }

  // Get feed
  getTweets();
  getFollowers();
});
