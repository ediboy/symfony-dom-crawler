<?php

namespace App;

use Symfony\Component\DomCrawler\Crawler;

require 'vendor/autoload.php';

class Scrape
{
    private $products = [];

    public function run(): void
    {
        $page = 1;
        $hasMoredata = true;

        // loop all pages until there is no more product page
        while ($hasMoredata) {
            $document = ScrapeHelper::fetchDocument('https://www.magpiehq.com/developer-challenge/smartphones?page='.$page);
            
            $products = $document->filter('.product');

            if(count($products) == 0) {
                $hasMoredata = false;
            }

            // loop all products
            $products->each(function (Crawler $node) {
                $variants = $node->filter('.px-2');

                // loop all variants
                $variants->each(function (Crawler $variant) use ($node){
                    $phone = new \stdClass();
                    $phone->title = $this->getTitle($node);
                    $phone->price = $this->getPrice($node);
                    $phone->imageUrl = $this->getImageUrl($node);
                    $phone->capacityMB = $this->getCapacityMB($node);
                    $phone->colour = $this->getColour($variant);
                    $phone->availabilityText = $this->getAvailabilityText($node);
                    $phone->isAvailable = $this->getIsAvailable($node);
                    $phone->shippingText = $this->getShippingText($node);
                    $phone->shippingDate = $this->getShippingDate($node);
                    
                    $exist = $this->checkIfExist($this->products, $phone);
                    
                    // don't add if duplicate
                    if(!$exist) {
                        $this->products[] = $phone;
                    }
                    
                });
               
            });

            $page = $page + 1;
          }
        

        file_put_contents('output.json', json_encode($this->products));
    }

    /**
     * Get title from node
     *
     * @param object $node
     * @return string
     */
    private function getTitle($node) {
        return $node->filter('div > h3')->text();
    }

    /**
     * Get price from node
     *
     * @param object $node
     * @return float
     */
    private function getPrice($node) {
        $price = ltrim($node->filter('.block.text-center.text-lg')->text(), '£');

        return (float)$price;
    }

    /**
     * Get image from node
     *
     * @param object $node
     * @return string
     */
    private function getImageUrl($node) {
        return $node->filter('img')->image()->getUri();
    }

    /**
     * Get capacity from node and convert to MB
     *
     * @param object $node
     * @return int
     */
    private function getCapacityMB($node) {
        $capacity = $node->filter('.product-capacity')->text();

        $capacity = strpos($capacity, 'GB') ? 
                        str_replace("GB","000",$capacity) : 
                        preg_replace("/[^0-9]/", "", $capacity);

        $capacity = str_replace(' ', '', $capacity);

        return (int)$capacity;
    }

    /**
     * Get colour from variant
     *
     * @param object $variant
     * @return string
     */
    private function getColour($variant) {
        return $variant->filter('.rounded-full')->attr('data-colour');
    }

    /**
     * Get availability text from node
     *
     * @param object $node
     * @return string
     */
    private function getAvailabilityText($node) {
        return $this->getIsAvailable($node) ? 'In Stock' : 'Out of Stock';
    }

    /**
     * Get is available from node
     *
     * @param object $node
     * @return bool
     */
    private function getIsAvailable($node) {
        $isAvailable = $node->filter('.my-4.text-sm.block.text-center')->first()->text();
        
        return (strpos($isAvailable, 'In Stock')) ? true : false;
    }

    /**
     * Get shipping text from node
     *
     * @param object $node
     * @return string
     */
    private function getShippingText($node) {
        $date = $this->convertDate($node);

        if($date) {
            return "Delivered from " . date('jS F', $date);
        }

        return '';
        
    }

    /**
     * Get shipping date from node
     *
     * @param object $node
     * @return string
     */
    private function getShippingDate($node) {
        $date = $this->convertDate($node);

        if($date) {
            return date('Y-m-d', $date);
        }

        return '';
    }

    /**
     * Convert date to time
     *
     * @param object $node
     * @return int|null
     */
    private function convertDate($node) {
        $posibleText = ['Delivery by ', 'Available on ', 'Delivery from ', 'Delivers ', 'Delivers '];
        
        $shippingNode = $node->filter('.my-4.text-sm.block.text-center')->eq(1);

        if(count($shippingNode)) {
            foreach ($posibleText as $text) {
                if (strpos($shippingNode->text(), $text) !== false) {
                    $date = str_replace($text, '', $shippingNode->text());
                    $date = strtotime($date);
                    return $date;
                }
            }
        }

        return null;
    }

    /**
     * Check if phone already exist
     *
     * @param array $products
     * @param object $phone
     * 
     * @return string
     */
    private function checkIfExist($products, $phone) {
       $phones =  array_filter($products, function($product) use ($phone){
            return ($product->title === $phone->title && $product->colour === $phone->colour);
         });

        return count($phones) ? true : false; 
    }

}

$scrape = new Scrape();
$scrape->run();
