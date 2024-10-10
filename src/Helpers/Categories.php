<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class Categories
{
    /**
     * Get all store categories
     *
     * @return array
     */
    public static function getCategories(): array
    {
        return [
            [
                'id'          => 'art',
                'description' => 'Collectibles & Art',
            ],
            [
                'id'          => 'baby',
                'description' => 'Toys for Baby, Stroller, Stroller Accessories, Car Safety Seats',
            ],
            [
                'id'          => 'coupons',
                'description' => 'Coupons',
            ],
            [
                'id'          => 'donations',
                'description' => 'Donations',
            ],
            [
                'id'          => 'computing',
                'description' => 'Computers & Tablets',
            ],
            [
                'id'          => 'cameras',
                'description' => 'Cameras & Photography',
            ],
            [
                'id'          => 'video games',
                'description' => 'Video Games & Consoles',
            ],
            [
                'id'          => 'television',
                'description' => 'LCD, LED, Smart TV, Plasmas, TVs',
            ],
            [
                'id'          => 'car electronics',
                'description' => 'Car Audio, Car Alarm Systems & Security, Car DVRs, Car Video Players, Car PC',
            ],
            [
                'id'          => 'electronics',
                'description' => 'Audio & Surveillance, Video & GPS, Others',
            ],
            [
                'id'          => 'automotive',
                'description' => 'Parts & Accessories',
            ],
            [
                'id'          => 'entertainment',
                'description' => 'Music, Movies & Series, Books, Magazines & Comics, Board Games & Toys',
            ],
            [
                'id'          => 'fashion',
                'description' => 'Men\'s, Women\'s, Kids & baby, Handbags & Accessories, Health & Beauty, Shoes, Jewelry & Watches',
            ],
            [
                'id'          => 'games',
                'description' => 'Online Games & Credits',
            ],
            [
                'id'          => 'home',
                'description' => 'Home appliances. Home & Garden',
            ],
            [
                'id'          => 'musical',
                'description' => 'Instruments & Gear',
            ],
            [
                'id'          => 'phones',
                'description' => 'Cell Phones & Accessories',
            ],
            [
                'id'          => 'services',
                'description' => 'General services',
            ],
            [
                'id'          => 'learnings',
                'description' => 'Trainings, Conferences, Workshops',
            ],
            [
                'id'          => 'tickets',
                'description' => 'Tickets for Concerts, Sports, Arts, Theater, Family, Excursions tickets, Events & more',
            ],
            [
                'id'          => 'travels',
                'description' => 'Plane tickets, Hotel vouchers, Travel vouchers',
            ],
            [
                'id'          => 'virtual goods',
                'description' => 'E-books, Music Files, Software, Digital Images, PDF Files and any item which can be electronically stored in a file, Mobile Recharge, DTH Recharge and any Online Recharge',
            ],
            [
                'id'          => 'others',
                'description' => 'Other categories',
            ],
        ];
    }
}
