# Integrating wrp-distributor into YOUR frontend #

You can "control" `wrp_distributor` by sending HTTP requests.

All requests:

- MUST HAVE a `Bearer` header set with the correct authentication token for the instance.
- CAN HAVE but SHOULD NOT HAVE a body, it's ignored anyway so save the bytes
- WILL RETURN 
	- a statuscode of 200 in case of GET
	- a statuscode of 202 for container start/stop actions (because of the delayed execution, exact delay between request and executions varies per config/install)
	- a statuscode of 204 for container lifetime extend requests
	- a statuscode of 400 in case your request doesn't make sense (or some requirements failed, unlikely)
	- a statuscode of 401 if your "Bearer" header was invalid
	- a statuscode of 405 if you used an unsupported HTTP method
	- a statuscode of 503 if the container could not be started (for PUT requests) or stopped (for DELETE requests)

Control is in quote since there is not much to be done. You will get it after some usage examples. Basically
everything is determined by the method of your HTTP request. All session handling is done automagically
by `wrp-distributor`.

## Important remarks and note ##

`wrp-distributor` manages client sessions based on IP-address (v6 supported, too) and user-agent. This is enforced with a unique database
key using the ip and userAgent fields. You most likely want to add a small "random per installation" value to the user-agent to make sure 
multiple user can use the distributor from the same IP address. You could derive it from the MAC maybe in example. 

Else each user from that IP would share the same `wrp` container. This would cause a heck of confusions with users, would be borderline 
useless - and very insecure. Yes, this is annoying but that's the price to avoid logins and keep the codebase simple and easy to maintain.

Also: in case you modify `wrp-distributor` for your project make sure to adhere to the license - you MUST publish your modified sources even
if you don't distribute them and use them only to provide a service. See LICENSE and README.md for details.


## API usage ##

All actions are executed via HTTP requests, where the method used defines the behavior. You don't need to care about logging in or anything,
just send the correct "Bearer" token in the headers. But make sure you keep the useragent note from above in mind!

Any not supported method will result in a 405 status. The supported methods are:
- GET
- HEAD
- PUT
- DELETE

In case of bugs you will very likely encounter a 400 or 500 status. If you're running `wrp-distributor` from
the provided docker image the output should include some helpful stack information so please 
include that in your bug report. In case you're running it on any other way, or better said without `xdebug`, you will be limited to the logs
available in `./logs`.

### GET ###

Prints a plain html status dashboard of the current instance. It shows the current sessions, containers and remaining containers.

#### Response ####
| Property      | Value                 |
|---------------|-----------------------|
| Status        | 200                   |
| Content-Type: | text/html             |
| Body:         | HTML with status info |

### HEAD ### 

Simply upserts the session, which causes the lastUsed timestamp to be set to the current date and time. This prevents this session from
being garbage collected / shutdown. 

Recommendations: Monitor user activity, as long as the user continue scrolling the content or is filling some form issue periodically a 
HEAD request to make sure the session doesn't get killed while the user is doing "work".

Important note: Currently there is no "cleanup" implemented, so the exact interval you should issue such request is not yet determined.
For now plan with like "every 5minutes unused sessions will be shutdown". I will not be shorter than that, but maybe it will be longer then that.
But definitly already implement this, cleanup will be implemented without prior notice!

#### Response ####
| Property      | Value |
|---------------|-------|
| Status        | 204   |
| Content-Type: | none  |
| Body:         | none  |

### PUT ###

Will check for next available free port and start a `wrp` container on it for the current session. The request will respond asap, but
starting the actual container can take some time. Considering the speed of modern servers and retro computers this will likely be still
faster then your app reacting to the response. But no kind of "time for the container to be active" can be assumed! You MUST check 
yourself if the instance is available yet.



#### Response ####
| Property      | Value                           |
|---------------|---------------------------------|
| Status        | 202 or 503, see above           |
| Content-Type: | text/xml or text/html           |
| Body:         | wrpInstanceUrl or error details |

##### Body on 202 example ##### 
`<xml><wrpUrl>somehost.tld:9999</wrpUrl></xml>`


### DELETE ###

Will stop the `wrp` container for the current session. The request will respond asap, but stopping the actual container can take some time. 
Considering the speed of modern servers and retro computers this will likely be still faster then your app reacting to the response. 
But no kind of "time for the container to be stopped" can be assumed! This should not have any implications for implementation I guess.

Recommendations: issue this request "onNetworkShutdown", "onWindowClose" and similar.


#### Response ####
| Property      | Value                 |
|---------------|-----------------------|
| Status        | 202 or 503, see above |
| Content-Type: | none or text/html     |
| Body:         | none or error details |

## Testing / manual fiddling ##

There are some `.http` files provided to run the requests right from phpStorm (IDEA). Alternatively: use the HTTP tool of your choice, even
the console of your browser will do. Just remember to set "Bearer" according to your `.env` file.
