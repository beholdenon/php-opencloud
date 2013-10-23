<?php
/**
 * PHP OpenCloud library.
 * 
 * @copyright 2013 Rackspace Hosting, Inc. See LICENSE for information.
 * @license   https://www.apache.org/licenses/LICENSE-2.0
 * @author    Jamie Hannaford <jamie.hannaford@rackspace.com>
 * @author    Glen Campbell <glen.campbell@rackspace.com>
 */

namespace OpenCloud\Tests\ObjectStore;

use OpenCloud\Common\Constants\Size;

/**
 * Description of ContainerTest
 * 
 * @link 
 */
class ContainerTest extends \OpenCloud\Tests\OpenCloudTestCase
{
    
    private $service;
    
    public function __construct()
    {
        $this->service = $this->getClient()->objectStoreService('cloudFiles', 'DFW');
    }
    
    private function getTestFilePath()
    {
        $path = '/tmp/php_sdk_test_file';
        if (!file_exists($path)) {
            file_put_contents($path, '.');
        }
        return $path;
    }
    
    public function test_Services()
    {
        $this->assertInstanceOf(
            'OpenCloud\ObjectStore\CDNService',
            $this->service->getContainer()->getCDNService()
        );
    }
    
    public function test_Get_Container()
    {
        $container = $this->service->getContainer('container1');

        $this->assertEquals('container1', $container->getName());
        $this->assertEquals('5', $container->getObjectCount());
        $this->assertEquals('3846773', $container->getBytesUsed());
        $this->assertFalse($container->hasLogRetention());
        
        $this->assertEquals('100000', $container->getCountQuota());
        $this->assertEquals('5000000', $container->getBytesQuota());
        
        $cdn = $container->getCdn();
        $this->assertInstanceOf('OpenCloud\ObjectStore\Resource\CDNContainer', $cdn);
        $this->assertEquals('tx82a6752e00424edb9c46fa2573132e2c', $cdn->getTransId());
        $this->assertFalse($cdn->hasLogRetention());
        $this->assertTrue($cdn->isCdnEnabled());
        
        $cdn->refresh();
        
        $this->assertEquals(
            'https://83c49b9a2f7ad18250b3-346eb45fd42c58ca13011d659bfc1ac1.ssl.cf0.rackcdn.com', 
            $cdn->getCdnSslUri()
        );
        $this->assertEquals(
            'http://081e40d3ee1cec5f77bf-346eb45fd42c58ca13011d659bfc1ac1.r49.cf0.rackcdn.com', 
            $cdn->getCdnUri()
        );
        $this->assertEquals(
            '259200', 
            $cdn->getTtl()
        );
        $this->assertEquals(
            'http://084cc2790632ccee0a12-346eb45fd42c58ca13011d659bfc1ac1.r49.stream.cf0.rackcdn.com', 
            $cdn->getCdnStreamingUri()
        );
    }
    
    /**
     * @expectedException OpenCloud\Common\Exceptions\NoNameError
     */
    public function test_Bad_Name_Url()
    {
        $container = $this->service->getContainer();
        $container->name = '';
        
        $container->getUrl();
    }
    
    /**
     * @expectedException OpenCloud\Common\Exceptions\CdnNotAvailableError
     */
    public function test_NonCDN_Container()
    {
        $container = $this->service->getContainer('container2');
        $container->getCdn();
    }
    
    public function test_Delete()
    {
        $container = $this->service->getContainer('container1');
        $container->delete(true);
    }
    
    public function test_Object_List()
    {
        $list = $this->service->getContainer('container1')->objectList();
        $this->assertInstanceOf('OpenCloud\Common\Collection', $list);
        $this->assertEquals(6, $list->count());
        $this->assertEquals('test_obj_1', $list->first()->getName());
    }
    
    public function test_Misc_Operations()
    {
        $container = $this->service->getContainer('container1');
        $this->assertInstanceOf(
            'Guzzle\Http\Message\Response',
            $container->enableLogging()
        );
        $this->assertInstanceOf(
            'Guzzle\Http\Message\Response',
            $container->disableLogging()
        );
        $container->enableCdn(500);
        $this->assertInstanceOf(
            'Guzzle\Http\Message\Response',
            $container->disableCdn()
        );
        $this->assertInstanceOf(
            'Guzzle\Http\Message\Response',
            $container->createStaticSite('<body>Hello world!</body>')
        );
        $this->assertInstanceOf(
            'Guzzle\Http\Message\Response',
            $container->staticSiteErrorPage('error.html')
        );
    }
    
    public function test_Get_Object()
    {
        $object = $this->service->getContainer('container1')->getObject('foobar');
        $this->assertInstanceOf('OpenCloud\ObjectStore\Resource\DataObject', $object);
        $this->assertEquals(
            'b0dffe8254d152d8fd28f3c5e0404a10', 
            (string) $object->getContent()
        );
        $this->assertEquals('foobar', $object->getName());
    }
    
    /**
     * @expectedException OpenCloud\Common\Exceptions\InvalidArgumentError
     */
    public function test_Upload_Multiple_Fails_Without_Name()
    {
        $container = $this->service->getContainer('container1');
        $container->uploadObjects(array(
            array('path' => '/foo')
        ));
    }
    
    /**
     * @expectedException OpenCloud\Common\Exceptions\InvalidArgumentError
     */
    public function test_Upload_Multiple_Fails_With_No_Data()
    {
        $container = $this->service->getContainer('container1');
        $container->uploadObjects(array(
            array('name' => 'test', 'baz' => 'something')
        ));
    }
    
    public function test_Upload_Multiple()
    {
        $container = $this->service->getContainer('container1');
        $responses = $container->uploadObjects(array(
            array('name' => 'test', 'body' => 'FOOBAR')
        ));
        $this->assertInstanceOf('Guzzle\Http\Message\Response', $responses[0]);
        
        
        $container->uploadObjects(array(
            array('name' => 'test', 'path' => $this->getTestFilePath())
        ));
    }
    
    public function test_Upload()
    {
        $this->assertInstanceOf(
            'OpenCloud\ObjectStore\Resource\DataObject',
            $this->service->getContainer('container1')->uploadObject('foobar', 'data')
        );
    }
    
    public function test_Large_Upload()
    {
        $options = array(
            'name' => 'new_object',
            'path' => $this->getTestFilePath(),
            'metadata' => array('author' => 'Jamie'),
            'partSize' => Size::MB * 20,
            'concurrency' => 3,
            'progress' => function($options) {
                var_dump($options);
            } 
        );
        
        $container = $this->service->getContainer('container1');
        $container->setupObjectTransfer($options);
    }
    
    public function test_Large_Upload_With_Body()
    {
        $options = array(
            'name' => 'new_object',
            'body' => 'foo'
        );
        
        $container = $this->service->getContainer('container1');
        $container->setupObjectTransfer($options);
    }
    
    /**
     * @expectedException OpenCloud\Common\Exceptions\InvalidArgumentError
     */
    public function test_Large_Upload_Fails_Without_Name()
    {
        $options = array(
            'path' => '/foo'
        );
        
        $container = $this->service->getContainer('container1');
        $container->setupObjectTransfer($options);  
    }
    
    /**
     * @expectedException OpenCloud\Common\Exceptions\InvalidArgumentError
     */
    public function test_Large_Upload_Fails_Without_Entity()
    {
        $options = array(
            'name' => 'new_object',
            'path' => '/' . rand(1,9999)
        );
        
        $container = $this->service->getContainer('container1');
        $container->setupObjectTransfer($options);  
    }
    
    public function test_Metadata()
    {
        $container = $this->service->getContainer('container1');
        $metadata = $container->retrieveMetadata();
        
        $this->assertEquals('Whaling', $metadata->getProperty('Subject'));
        $this->assertEquals(
            $container->getMetadata()->getProperty('Subject'), 
            $metadata->getProperty('Subject')
        );
        
        $response = $container->unsetMetadataItem('Subject');
    }
    
}