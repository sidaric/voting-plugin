jQuery(document).ready(function($) {
    var voted = false; // To prevent further voting.
    var userVote = null; // To store the user's vote.

    // Access the post_id from the localized variable.
    var post_id = voting_plugin_ajax.post_id;

    // Function to check if the user has already voted on page load
    function checkUserVoteStatus() {
        var data = {
            action: 'check_user_vote_status', // Add a new AJAX action to check the user's vote status
            post_id: post_id,
        };

        $.post(voting_plugin_ajax.ajaxurl, data, function(response) {
            if (response.has_voted) {
                $('#voting-text').text('Thank you for your feedback.');
                $('#yes-text').text(response.yes_percentage + '%');
                $('#no-text').text(response.no_percentage + '%');
                voted = true;
                userVote = response.user_vote; // Store the user's vote
                updateButtonStyles();
            }
        });
    }

    // Call the function to check user vote status on page load
    checkUserVoteStatus();

    $('#vote-yes, #vote-no').on('click', function() {
        if (voted) {
            return; // Prevent further voting.
        }

        var vote = $(this).attr('id');
        var clickedButton = $(this); // Store a reference to the clicked button
        var otherButton = $(this).siblings(); // Find the other button (not clicked)

        var data = {
            action: 'vote_article',
            post_id: post_id,
            vote: vote,
        };

        $.post(voting_plugin_ajax.ajaxurl, data, function(response) {
            if (response.success) {
                $('#voting-text').text('Thank you for your feedback.');
                $('#yes-text').text(response.yes_percentage + '%');
                $('#no-text').text(response.no_percentage + '%');
                clickedButton.addClass("voted");
                otherButton.addClass("not-voted");
                userVote = vote; // Update the user's vote
                updateButtonStyles();
                voted = true;
                $('#vote-yes, #vote-no').off('click');
            } else {
                alert(response.message);
            }
        });
    });

    // Function to update button styles based on user's vote
    function updateButtonStyles() {
        if (userVote === 'vote-yes') {
            $('#vote-yes img').attr('src', '/wp-content/plugins/article-feedback-voting/icons/smile.svg');
            $('#vote-no img').attr('src', '/wp-content/plugins/voting/article-feedback-voting/sad-grey.svg');
            $('#vote-yes').addClass("voted");
            $('#vote-no').addClass("not-voted");
        } else if (userVote === 'vote-no') {
            $('#vote-yes img').attr('src', '/wp-content/plugins/voting/article-feedback-voting/smile-grey.svg');
            $('#vote-no img').attr('src', '/wp-content/plugins/voting/article-feedback-voting/sad.svg');
            $('#vote-no').addClass("voted");
            $('#vote-yes').addClass("not-voted");
        }
    }
});