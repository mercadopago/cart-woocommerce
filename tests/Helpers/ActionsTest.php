<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Helpers\Actions;
use stdClass;

class ActionsTest extends TestCase
{
    public $actions;

    public function setUp(): void
    {
        $this->actions = new Actions();
    }

    /* Give register the action correctly when classes and methods exist. */
    public function testRegisterActionWhenGatewayIsNotCalledSuccess()
    {
        $hookMethod = 'hookTest';
        $gatewayMethod = 'gatewayTest';

        $hook = $this->getMockBuilder(stdClass::class)
            ->addMethods([$hookMethod])
            ->getMock();

        $gateway = $this->getMockBuilder(stdClass::class)
            ->addMethods([$gatewayMethod])
            ->getMock();

        $hook->expects($this->once())
            ->method($hookMethod)
            ->will($this->returnCallback(function ($callback) use ($gateway, $gatewayMethod) {
                $gateway->method($gatewayMethod);

                $callback();
            }));

        $this->actions->registerActionWhenGatewayIsNotCalled($hook, $hookMethod, get_class($gateway), $gatewayMethod);
    }


    /* The hook class method should not be called when the gateway class does not exist */
    public function testRegisterActionWhenGatewayIsNotCalledNoGatewaysClass()
    {
        $gateway = 'GatewayTestFail';

        $hookMethod = 'hookMethodTest';
        $gatewayMethod = 'gatewayMethodTest';

        $hook = $this->getMockBuilder(stdClass::class)
            ->addMethods([$hookMethod])
            ->getMock();


        $hook->expects($this->never())
            ->method($hookMethod);

        $this->actions->registerActionWhenGatewayIsNotCalled(
            $hook,
            $hookMethod,
            $gateway,
            $gatewayMethod
        );
    }
}
