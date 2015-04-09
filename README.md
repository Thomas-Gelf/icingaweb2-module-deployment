# Icinga Web 2 - deployment module

This Icinga Web 2 module wants to assist your deployment workflows. You want
to run real world behaviour tests carried out by your monitoring system on
demand? Triggered by your CI platform? Then this module might be a perfect
fit.

All you want to achieve is allowing Windows hosts to schedule their downtime
by themselves on reboot? Here you go.

## Installation

Like with any other Icinga Web 2 module just drop me to one of your module
folders and enable the `deployment` module in your web frontend or on CLI. Of
course the `monitoring` module needs to be enabled and that's it, we have no
farther dependencies.

## Configuration

As Icinga Web 2 offers no generic API interface (not yet), this module bypasses
authention. To get access to the deployment module you need to pass a token
string configured in this modules config file. This file has to be in
`ICINGAWEB_CONFIGDIR/modules/deployment/config.ini`:

```ini
[auth]
token = insecure
```

## URLs

All shown URLs are relative to your base Icinga Web 2 url, so deployment/hello
could be https://&lt;monitoring&gt;/icingaweb/deployment/hello in your environment.

## Schedule downtimes

The base URL is deployment/downtime/schedule, the only required parameter is
`host`, carrying the Icinga host name:

    deployment/downtime/schedule?host=localhost

Calling this URL would schedule a flexible downtime allowed to start within
the next 15 minutes with a maximum duration of 20 minutes. The output would
look as follows:

> localhost
> ---------
> Scheduled a flexible downtime with a maximum duration of 20m 0s allowed
> to start between 2014-12-18 12:32:36 and 2014-12-18 12:47:36 saying
> "System went down for reboot".

Your deployment tool might call this action as follows:

    deployment/downtime/schedule?host=localhost
      &comment=Deploying%20new%20version
      &duration=1800

### Available parameters

* **start**: define when your downtime should start. Default is NOW. Allowed
  values are Unix timestamps, but expressions like `+1day` are also fine.
  Please take care about URL escaping when using them
* **end**: define the end of this downtime. For flexible downtimes this means
  that the host must enter it's problem state before `end` is reached. Default
  is start plus 15 minutes, values are absolute timestamp or expressions as
  for `start`
* **duration**: maximum duration of this flexible downtime
* **comment**: a custom comment, default is "System went down for reboot"

## Remove downtimes

To remove a downtime for safety measures you are required to pass the very
same comment you used to schedule your downtime. For the downtime shown above
this would read:

    deployment/downtime/remove?host=localhost&comment=Deploying%20new%20version


## Full workflow example

GET http://icingaweb/icingaweb/deployment/health/check?host=localhost&token=insecure&json

```json
{
    "success": true,
    "healthy": true,
    "host": {
        "host": "localhost",
        "state": "0",
        "problem": "0",
        "output": "PING OK - Packet loss = 0%, RTA = 0.06 ms",
        "handled": "0",
        "in_downtime": "0",
        "acknowledged": "0",
        "last_check": "1427989288",
        "next_check": "1427989338"
    },
    "service": [
        {
            "host": "localhost",
            "service": "Ping",
            "state": "0",
            "problem": "0",
            "output": "PING OK - Packet loss = 0%, RTA = 0.05 ms",
            "handled": "0",
            "in_downtime": "0",
            "acknowledged": "0",
            "last_check": "1427989309",
            "next_check": "1427989365"
        },
        {
            "host": "localhost",
            "service": "SSH",
            "state": "0",
            "problem": "0",
            "output": "SSH OK - OpenSSH_6.0p1 Debian-4+deb7u2 (protocol 2.0) ",
            "handled": "0",
            "in_downtime": "0",
            "acknowledged": "0",
            "last_check": "1427989314",
            "next_check": "1427989364"
        }
    ]
}
```

POST http://icingaweb/icingaweb/deployment/downtime/schedule?host=localhost&comment=deployment&token=insecure&json

```json
{
    "success": true,
    "host": "localhost",
    "downtime_comment": "deployment",
    "downtime_duration": 1200,
    "downtime_start": 1427989338,
    "downtime_end": 1427990238
}
```

POST http://icingaweb/icingaweb/deployment/downtime/remove?host=localhost&comment=deployment&token=insecure&json

```json
{
    "success": true,
    "total": 3,
    "downtimes": [
        {
            "host": "localhost",
            "service": null,
            "objecttype": "host",
            "internal_id": "11"
        },
        {
            "host": "localhost",
            "service": "SSH",
            "objecttype": "service",
            "internal_id": "12"
        },
        {
            "host": "localhost",
            "service": "Ping",
            "objecttype": "service",
            "internal_id": "13"
        }
    ]
}
```

POST http://icingaweb/icingaweb/deployment/health/check?host=localhost&token=insecure&checkNow&json

```json
{
    "success": true,
    "healthy": false,
    "host": {
        "host": "localhost",
        "state": "0",
        "problem": "0",
        "output": "PING OK - Packet loss = 0%, RTA = 0.06 ms",
        "handled": "0",
        "in_downtime": "0",
        "acknowledged": "0",
        "last_check": "1427991382",
        "next_check": "1427991438"
    },
    "service": [
        {
            "host": "localhost",
            "service": "Ping",
            "state": "0",
            "problem": "0",
            "output": "PING OK - Packet loss = 0%, RTA = 0.05 ms",
            "handled": "0",
            "in_downtime": "0",
            "acknowledged": "0",
            "last_check": "1427991409",
            "next_check": "1427991465"
        },
        {
            "host": "localhost",
            "service": "SSH",
            "state": "2",
            "problem": "1",
            "output": "Connection refused",
            "handled": "0",
            "in_downtime": "0",
            "acknowledged": "0",
            "last_check": "1427991404",
            "next_check": "1427991434"
        }
    ]
}
```

