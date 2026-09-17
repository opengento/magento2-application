# Application Module for Magento 2

[![Latest Stable Version](https://img.shields.io/packagist/v/opengento/module-application.svg?style=flat-square)](https://packagist.org/packages/opengento/module-application)
[![License: MIT](https://img.shields.io/github/license/opengento/magento2-application.svg?style=flat-square)](./LICENSE)
[![Packagist](https://img.shields.io/packagist/dt/opengento/module-application.svg?style=flat-square)](https://packagist.org/packages/opengento/module-application/stats)
[![Packagist](https://img.shields.io/packagist/dm/opengento/module-application.svg?style=flat-square)](https://packagist.org/packages/opengento/module-application/stats)

This module allows the application to reuse the bootstrap and reset its state after the request.

- [Setup](#setup)
    - [Composer installation](#composer-installation)
    - [Setup the module](#setup-the-module)
- [Features](#features)
- [Support](#support)
- [Authors](#authors)
- [License](#license)

## Setup

Magento 2 Open Source or Commerce edition is required.

### Composer installation

Run the following composer command:

```
composer require opengento/module-application
```

### Setup the module

Run the following magento command:

```
bin/magento setup:upgrade
```

**If you are in production mode, do not forget to recompile and redeploy the static resources.**

## Features

This module allows the application to reuse the bootstrap and reset its state after the request.

The Application Boostrap can be used to launch the application with different loop HTTP server like FrankenPHP, Swoole, ReactPHP or via Fiber.

State left behind by one request is reset before the next on a persistent worker. For how that works — and the PHP 8.4 lazy-ghost pitfall when extending the reset surface — see [Resetting state between requests](./docs/state-reset.md).

## Support

Raise a new [request](https://github.com/opengento/magento2-application/issues) to the issue tracker.

## Authors

- **Opengento Community** - *Lead* - [![Twitter Follow](https://img.shields.io/twitter/follow/opengento.svg?style=social)](https://twitter.com/opengento)
- **Thomas Klein** - *Maintainer* - [![GitHub followers](https://img.shields.io/github/followers/thomas-kl1.svg?style=social)](https://github.com/thomas-kl1)
- **Contributors** - *Contributor* - [![GitHub contributors](https://img.shields.io/github/contributors/opengento/magento2-application.svg?style=flat-square)](https://github.com/opengento/magento2-application/graphs/contributors)

## License

This project is licensed under the MIT License - see the [LICENSE](./LICENSE) details.

***That's all folks!***
