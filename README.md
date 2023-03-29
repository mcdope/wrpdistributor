[![CI (Build, phpcs, psalm, phpunit)](https://github.com/guthand/tobi_wrpdistributor/actions/workflows/build.yml/badge.svg)](https://github.com/guthand/tobi_wrpdistributor/actions/workflows/build.yml)

### What the heck is this? ### 

WrpDistributor is a small application to allow https://github.com/alb42/wrp to be used by multiple clients via 
a client application like [AmiFox](https://blog.alb42.de/2023/02/12/browsing-the-web-amifox/).

### License ###

This whole project (regarding this script to distribute sessions to multiple `wrp` instances) is licensed under AGPLv3. You can find 
the license text at https://www.gnu.org/licenses/agpl-3.0.de.html. Basically, it means it's opensource, and you can do 
whatever you want, as long as you make your changes opensource again. Of course, preferred as a pull request, but to be 
 compliant, it's enough to publish your sources. And yes, this also applies if you don't distribute it — it's AGPL, not GPL.

Note that this DOESN'T apply to `AmiFox` (the reference/main implementation) itself, which is a closed source project.

### How do I set it up? ###

#### The easy way ####
Download the repository, open a shell and change to the repository. Now...
- run `cp .env.dist .env`
- edit `.env` and set your parameters
    - the defaults are assuming you run it via the provided docker image, so you only need to change:
       - `MAX_CONTAINERS_RUNNING`
       - `CONTAINER_HOSTS`
       - `CONTAINER_HOSTS_KEYS`
       - `CONTAINER_HOSTS_TLS_CERTS`
       - `AUTH_TOKEN`
       - ... and maybe `START_PORT` and `CONTAINER_DISTRIBUTION_METHOD`
- run `make build` to create the docker container
- run `make up`, it will start the container and provide `wrp-distributor` on the public port `7777`
- actually set up at least one containerHost
    - configure the server of your choice (as long as it's Linux 😅) with Docker
    - add a user dedicated to run the containers, add it to the group `docker` to allow it to manage containers
        - IMPORTANT: this allows this user to manage ALL containers on that host, so you most likely want to put this in a VM itself, lock this user down further and allow logins only from your expected ingress IP.
    - create an SSH keypair to allow `wrp-distributor` to access the host(s). If you configure multiple hosts, you should use a dedicated keypair for each of them. Then install this key with `ssh-copy-id` to the user account of the host
    - make sure you can ssh with that key into the host
    - make sure you can run docker with that user on that host (i.e. run the helloworld container)
    - put the keypair into `./ssh`
    - adjust your `.env`
       - add host to `CONTAINER_HOSTS`, either as DNS name or IP
       - adjust `MAX_CONTAINERS_RUNNING`
       - add authentication data to `CONTAINER_HOSTS_KEYS`
           - format is `userName~filenameOfPrivateKey`
               - if your key is password protected, it would be `userName~filenameOfPrivateKey~keyPassword`
       - add TLS certificate and private key to `CONTAINER_HOSTS_TLS_CERTS`
- Repeat the previous step for as many hosts as you want
- container related `.env` vars like `MAX_CONTAINERS_RUNNING` must always contain the same number of elements (you will get an exception else)
- Set `CONTAINER_DISTRIBUTION_METHOD` to control the load balancing. The first host used will always be random. Available values:
    - `equal` - will distribute containers equally over all hosts. To use this all hosts should have the same value set in `MAX_CONTAINERS_RUNNING`, everything else is untested
        - if all hosts currently run the same number of containers, the next one will be chosen randomly
    - `fillhost` - will fill the first host, then use the next and so on (the default)
- In case you're running this in a production setup: provide a reverse proxy to expose `wrp-distributor` via your default webserver. Make sure you don't leak sensitive data like SSH keys etc.

#### The hard way ####

You will need MySQL 8, PHP8.2 and composer on your server to run this. 

- put `wrp-distributor` in the content root you want to use
- open a shell and switch to your content root
- run `cp .env.dist .env`
- run `composer install` to install the dependencies
- create a database and database user for `wrp-distributor` and adjust `.env` accordingly
    - required tables will be auto-created if missing, don't worry about it
- adjust remaining parameters in `.env` according to your needs
- continue with "actually set up at least one containerHost" from [The easy way](README.md#the-easy-way-)

#### Stuff you need to figure out yourself ####

You should clean up unused sessions sometimes. For this there is a cli command provided - `./bin/console cleanup:sessions`.
It checks every session for age and will shut down the container (if there is one) and delete the session afterward.
Optionally you can specify the timeout for sessions in minutes as an argument, the default is 10, so every session
not used for at least 10 minutes will be terminated by default.

If you're running `wrp-distributor` via Docker you can just invoke `make cleanup_session` instead. If you
run a self-configured setup, you need to figure it out on your own.

### How do I use it? 

See [INTEGRATION.md](INTEGRATION.md)

#### Huh? I don't get it... ####

Then this software isn't for you. You're most likely looking for https://github.com/alb42/wrp instead.

### How do I...? ###

#### Configure my containerHosts in a secure way ####

You won't if you need to ask. There is too much to be missed and profound knowledge about server setup required.

Points you should look into:
- limit user to only `docker`, disallow everything else (though the ability to use docker kinda defeats that)
- disallow password login for that user
- allow login only from the expected ingress IP (= your `wrp-distributor` host)

### TODO ###
- add purpose-bound Exception classes (in progress)
- strip down php container, guess we don't need most extensions
- move docker image name to env var
- make servicecontainer selfcontained (instantiate everything in constructor)
- put cronjobs into docker container
- extract loadbalancing into own service / strategy classes
- "fix" findUnusedPort