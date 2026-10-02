# beffroi/op

The standalone identity provider: a Symfony skeleton on `beffroi/symfony-bundle`, served by FrankenPHP,
with PostgreSQL and RabbitMQ behind it. It is the recommended deployment, the form the provider takes for
whoever runs one rather than installs it inside an application of their own.

The packages it installs live in [beffroi-php/beffroi](https://github.com/beffroi-php/beffroi), the identity
core, the OAuth 2.0 and OpenID Connect module and the Symfony bundle. The design and the decisions are in
[beffroi-php/design](https://github.com/beffroi-php/design), and the reference relying party
in [beffroi-php/oidc-demo](https://github.com/beffroi-php/oidc-demo).

## What is here, and what is not

Here: what a deployment decides. The layout the pages of the bundle take and the stylesheet that gives
them their look (Tailwind and DaisyUI), the firewall, the Messenger contract and its transports, the
migrations, the FrankenPHP and Caddy configuration with the host that asks for a client certificate, and
the authorization detail types this deployment wires.

Not here: the protocol and the identity model, which are the packages; and the development stack, the
castor tasks, the conformance plans and the demonstration walks, which live in the monorepo and drive
this application from a working copy.

## Working on it

This repository is cloned inside a working copy of the monorepo, at `apps/op`, which that repository
ignores. Everything that builds, provisions, judges or demonstrates runs from the root above:

```bash
git clone git@github.com:beffroi-php/beffroi.git && cd beffroi
git clone git@github.com:beffroi-php/op.git apps/op
composer install && (cd apps/op && composer install)
castor start            # https://op.localhost, PostgreSQL, RabbitMQ
castor provision        # the tenant, its keys, the first user, the client certificate
```

`beffroi/*` is resolved by path repositories to `../../src/*`, so a clone on its own installs nothing: the
application is worked on inside the monorepo, and deployed from a build rather than from a clone.

## Issues

Issues about the application are here. The packages, the stack, the judges and the spikes are tracked in
the monorepo.

## Licence

Its `composer.json` declares MIT, as the packages do. The repository is private.
