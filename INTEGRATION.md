# Integrating wrp-distributor into YOUR frontend #

You can "control" `wrp_distributor` by sending HTTP requests.

All requests:

- MUST HAVE a `Bearer` header set with the correct authentication token for the instance.
- CAN HAVE but SHOULDN'T HAVE a body, with PUT being an exception to that rule
- WILL RETURN 
	- a statuscode of 200 in case of GET
	- a statuscode of 202 for container start/stop actions (because of the delayed execution, exact delay between request and executions varies per config/install)
        - a statuscode of 204 if you PUT a session that already has an container
	- a statuscode of 204 for container lifetime extend requests
	- a statuscode of 400 in case your request doesn't make sense (or some requirements failed, unlikely)
	- a statuscode of 401 if your "Bearer" header was invalid
	- a statuscode of 405 if you used an unsupported HTTP method
    - a statuscode of 500 in case of a critical startup error
	- a statuscode of 503 if the container couldn't be started (for PUT requests) or stopped (for DELETE requests)

Control is in quote since there is not much to be done.
You will get it after some usage examples. 
Basically, everything is determined by the method of your HTTP request.
Session handling is done automagically by `wrp-distributor`.

## Important remarks and note ##

`wrp-distributor` manages client sessions based on IP-address (v6 supported, too) and user-agent. This is enforced with a unique database
key using the ip and userAgent fields. You most likely want to add a small "random per installation" value to the user-agent to make sure 
multiple users can use the distributor from the same IP address. You could derive it from the MAC maybe, in example. 

If you don't do this each user from that IP would share the same `wrp` container.
This would cause a heck of confusion with users, would be borderline useless — and very insecure.
Yes, this is maybe annoying for as a client dev, but that's the price to avoid logins and keep the codebase simple and easy to maintain.

Also: in case you modify `wrp-distributor` for your project make sure to adhere to the license - you MUST publish your modified sources even
if you don't distribute them and use them only to provide a service. See LICENSE and README.md for details.

## Examples of integrating applications ##
- [AmiFox](https://blog.alb42.de/2023/02/12/browsing-the-web-amifox/) 

## API usage ##

All actions are executed via HTTP requests, where the method used defines the behavior. You don't need to care about logging in or anything,
just send the correct "Bearer" token in the headers. But make sure you keep the useragent note from above in mind!

Any not supported method will result in a 405 status. The supported methods are:
- GET
- HEAD
- PUT
- DELETE

In case of bugs, you will very likely encounter a 400 or 500 status.

If you're running `wrp-distributor` from
the provided docker image the output should include some helpful stack information so please 
include that in your bug report.
In case you're running it on any other way, or better said without `xdebug`, you will be limited to the logs
available in `./logs`.

### GET ###

Prints a plain html status dashboard of the current instance. It shows the current sessions, containers and remaining containers.
Mainly intended for local testing.

#### Response ####
| Property      | Value                 |
|---------------|-----------------------|
| Status        | 200, 503 on error     |
| Content-Type: | text/html             |
| Body:         | HTML with status info |

### HEAD ### 

Simply upserts the session, which causes the lastUsed timestamp to be set to the current date and time. This prevents this session from
being garbage collected / shutdown. Such a request should be sent at least once every 9 minutes (when using the default timeout of 10 minutes).

Recommendations: Monitor user activity,
as long as the user continues scrolling the content or is filling some form issue periodically a 
HEAD request to make sure the session doesn't get killed while the user is doing "work".

#### Response ####
| Property      | Value             |
|---------------|-------------------|
| Status        | 204, 503 on error |
| Content-Type: | none              |
| Body:         | none              |

### PUT ###

Will check for next available free port and start a `wrp` container on it for the current session. The request will respond asap, but
starting the actual container can take some time. Considering the speed of modern servers and retro computers, this will likely be 
faster than your app reacting to the response. But no kind of "time for the container to be active" can be assumed! You MUST check 
yourself if the instance is already available. To control the WRP instance you MUST send the value of `token` in the `Bearer` header
in each request to WRP, else it will return `401`.

You can include `ssl=true` in your request body to get a TLS secured `wrp`. The default is to not use TLS.



#### Response ####
| Property      | Value                                                     |
|---------------|-----------------------------------------------------------|
| Status        | 202, 204 (if container already running) or 503, see above |
| Content-Type: | text/xml or text/html                                     |
| Body:         | wrpInstanceUrl and token or error details                 |

##### Body on 202 example ##### 
`<xml><wrpUrl>somehost.tld:9999</wrpUrl><token>stringYouMustSetInBearerHeaderOnRequestsToWRP</token></xml>`


### DELETE ###

Will stop the `wrp` container for the current session. The request will respond asap, but stopping the actual container can take some time. 
Considering the speed of modern servers and retro computers, this will likely be faster than your app reacting to the response. 
But no kind of "time for the container to be stopped" can be assumed! This shouldn't have any implications for implementation, I guess.
In case there is no container running for the current session it will return 204 instead of 202 on success.

Recommendations: issue this request "onNetworkShutdown", "onWindowClose" and/or similar events.


#### Response ####
| Property      | Value                      |
|---------------|----------------------------|
| Status        | 202, 204 or 503, see above |
| Content-Type: | none or text/html          |
| Body:         | none or error details      |

## Testing / manual fiddling ##

There are some `.http` files provided to run the requests right from phpStorm (IDEA). Alternatively: use the HTTP tool of your choice, even
the console of your browser will do. Just remember to set "Bearer" according to your `.env` file.
