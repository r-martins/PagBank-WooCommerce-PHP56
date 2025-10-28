<php

namespace RM_PagBank\Tests\Connect\Payments;

// use RM_PagBank\Connect\Payments\Pix; // PHP 5.6 compatibility
// use WC_Helper_Order; // PHP 5.6 compatibility

class PixTest extends \WP_UnitTestCase
{

    /**
     * @covers \RM_PagBank\Connect\Payments\Pix::prepare
     * @return void
     */
    public function testPrepare()
    {
        $order = WC_Helper_Order::create_order();
        $pix = new Pix($order);
        $params = $pix->prepare();

		$this->assertArrayHasKey('qr_codes', $params);
        $this->assertTrue(true);
    }
}
