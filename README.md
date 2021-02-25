# tweetSample
Command line script to output tweets

Here is a small PHP script that runs on the command line and outputs tweets to the standard output.

Please find the documentation at

https://dev.twitter.com/docs/api/1.1/get/statuses/sample

The script should understand two input parameters:

* `--n`: exit after *n* tweets were outputted
* `--t`: exit after *t* seconds (doesn‘t has to be exact)

Their default values are 0: in that case it runs indefinitely.

The output should contain the username and the text of the tweet.
