# beffroi/beffroi

The identity provider: a Symfony application served by FrankenPHP, with PostgreSQL behind it, whose tables are
the queues of its lanes as well: it carries the transport of no broker. It is the one form the provider is
deployed in; nothing installs it inside another application.

It is the `apps/beffroi` directory of the monorepo, [beffroi-auth/beffroi](https://github.com/beffroi-auth/beffroi),
beside the packages it installs: the identity core, the OAuth 2.0 and OpenID Connect module and the SAML
2.0 module. `beffroi-auth/application` is a mirror of that directory, read only, pushed by git split. Its
documentation is at [doc.beffroi.tech](https://doc.beffroi.tech), and the reference relying party is
[beffroi-auth/oidc-demo](https://github.com/beffroi-auth/oidc-demo).

## What is here, and what is not

Here: the provider as a Symfony application on the packages. The `beffroi:` tree its kernel registers
(`src/DependencyInjection/`) and the wiring it loads (`config/beffroi/`); the controllers, the security, the
persistence and the migrations; the pages (`templates/beffroi/`), the layout they take and the stylesheet
that gives them their look (Tailwind and DaisyUI); the firewall, the Messenger contract and its transports,
and the FrankenPHP and Caddy configuration with the host that asks for a client certificate. Its suite is
here too, in `tests/`: it boots this kernel, on the database and the KMS that kernel needs. The suite of the
integration, at the root of the monorepo (`tests/symfony`), boots the same wiring in a kernel of its own. And what the development stack reads of this directory and no deployment
serves: the configuration of development, the demonstration of the flows (`src/Demonstration/`) and what the
tasks of the lab ask of the application (`src/Lab/`), the one type of authorization detail the stack wires
among it. The image carries none of that, nor the suite.

Not here: the protocol and the identity model, which are the packages; and the development stack, the
castor tasks, the conformance plans and the demonstration walks, which sit at the root of the monorepo and
drive this application from a working copy.

## Working on it

Everything that builds, provisions, judges or demonstrates runs from the root above:

```bash
git clone git@github.com:beffroi-auth/beffroi.git && cd beffroi
composer install && (cd apps/beffroi && composer install)
castor start            # https://op.localhost, PostgreSQL
castor provision        # the tenant, its keys, the first user, the client certificate
castor app-phpunit      # the suite of this directory, against the PostgreSQL the stack publishes
```

`beffroi/*` is resolved by path repositories to `../../src/*`, so this directory installs nothing on its
own: the application is worked on inside the monorepo, and deployed from a build rather than from a clone.
A deployment runs the published image, `ghcr.io/beffroi-auth/beffroi`, with `compose.prod.yaml` at the root of the
monorepo: the provider, its worker and PostgreSQL, the file saying in its header what it needs and the order of
the first start.

## Issues and pull requests

They are the monorepo's, the application's as much as the packages'. A mirror is read only.

## Licence

Its `composer.json` declares MIT, as the packages do.
