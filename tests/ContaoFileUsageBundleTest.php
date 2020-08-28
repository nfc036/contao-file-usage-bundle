<?php

/*
 * This file is part of [package name].
 *
 * (c) John Doe
 *
 * @license LGPL-3.0-or-later
 */

namespace Nfc036\ContaoFileUsageBundle\Tests;

use Nfc036\ContaoFileUsageBundle\ContaoFileUsageBundle;
use PHPUnit\Framework\TestCase;

class ContaoFileUsageBundleTest extends TestCase
{
    public function testCanBeInstantiated()
    {
        $bundle = new ContaoFileUsageBundle();

        $this->assertInstanceOf('Nfc036\ContaoFileUsageBundle\ContaoFileUsageBundle', $bundle);
    }
}
