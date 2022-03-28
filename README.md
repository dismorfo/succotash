# hidvl-pnxs

#### Install

You need https://getcomposer.org/doc/00-intro.md#locally to install dependencies. Once `composer` is installed, run `composer install`.

#### ENV

App requires an environment variable file (`.env`) with the variables:

```
APP_ROOT=/
GA=0
GAUA=UA-XXXXXXXX-X
endpoint=
```

#### Test drive

If you have PHP in your system, you can run the built-in web server. E.g., `php --server localhost:5000`.

#### Test URL

Open the URL http://localhost:5000/47d7wmjw and you should see a video embed, the title of the resource and other metadata requested from PNXS.
