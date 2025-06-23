<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Translations\AdminTranslations;

if (!defined('ABSPATH')) {
    exit;
}

class CredentialsStates
{
    private Seller $sellerConfig;

    private AdminTranslations $adminTranslations;

    private const NOT_LINKED_FAILED = 'not_linked_failed';

    private const EXPIRED = 'expired';

    private const COULD_NOT_VALIDATE_LINK = 'could_not_validate_link';

    public const UNAUTHORIZED_ACCESS_TOKEN = 'unauthorized_access_token';

    private const LINKED_NO_TEST_CREDENTIALS = 'linked_no_test_credentials';

    private const PREVIOUSLY_LINKED = 'previously_linked';

    private const RECENTLY_LINKED = 'recently_linked';

    private const LINKED_FAILED_TO_LOAD = 'linked_failed_to_load';

    private const LINK_UPDATED = 'link_updated';

    private const LINKED = 'linked';

    private const CREDENTIALS_STATE = 'credentials_state';

    private const TYPE = 'type';

    private const TITLE = 'title';

    private const DESCRIPTION = 'description';

    private const STORE_NAME = 'store_name';

    private const STORE_CONTACT = 'store_contact';

    private const LINKED_ACCOUNT = 'linked_account';

    private const BUTTON = 'button';

    private const SECONDARY_BUTTON = 'secondary_button';

    private const MORE_INFO = 'more_info';

    private const LINKED_DATA = 'linked_data';

    private const STATUS = 'status';

    private const DATA = 'data';

    private const SUCCESS = 'success';

    private const ERROR = 'error';

    private const NOT_LINKED = 'not_linked';

    private const FAILED = 'failed';

    private const UNAUTHORIZED = 'unauthorized';

    private const UPDATE = 'update';

    private const LINKED_TITLE = 'linked_title';

    private const LINKED_DESCRIPTION = 'linked_description';

    private const LINKED_FAILED_TO_LOAD_STORE_NAME = 'linked_failed_to_load_store_name';

    private const LINKED_FAILED_TO_LOAD_STORE_CONTACT = 'linked_failed_to_load_store_contact';

    private const FAILED_TITLE = 'failed_title';

    private const FAILED_DESCRIPTION = 'failed_description';

    private const FAILED_BUTTON = 'failed_button';

    private const UPDATE_TITLE = 'update_title';

    private const UPDATE_DESCRIPTION = 'update_description';

    private const UPDATE_BUTTON = 'update_button';

    private const LINK_UPDATED_TITLE = 'link_updated_title';

    private const LINK_UPDATED_DESCRIPTION = 'link_updated_description';

    private const PREVIOUSLY_LINKED_TITLE = 'previously_linked_title';

    private const PREVIOUSLY_LINKED_DESCRIPTION = 'previously_linked_description';

    private const COULD_NOT_VALIDATE_LINK_TITLE = 'could_not_validate_link_title';

    private const COULD_NOT_VALIDATE_LINK_DESCRIPTION = 'could_not_validate_link_description';

    private const APP_NAME = 'app_name';

    private const NICKNAME = 'nickname';

    private const EMAIL = 'email';

    private const LINKED_BUTTON = 'linked_button';

    private const LINKED_MORE_INFO = 'linked_more_info';

    private const CURRENT_SITE_ID = 'current_site_id';

    private const PERIOD = 'period';

    /**
     * @param AdminTranslations $adminTranslations
     * @param Seller $sellerConfig
     */
    public function __construct(
        AdminTranslations $adminTranslations,
        Seller $sellerConfig
    ) {
        $this->adminTranslations = $adminTranslations;
        $this->sellerConfig = $sellerConfig;
    }

    public function getCredentialsTemplate($linkStatus): array
    {
        $linkedStatuses = [
            self::RECENTLY_LINKED,
            self::LINK_UPDATED,
            self::PREVIOUSLY_LINKED,
            self::LINKED_NO_TEST_CREDENTIALS,
            self::LINKED_FAILED_TO_LOAD,
            self::LINKED
        ];

        if (in_array($linkStatus, $linkedStatuses)) {
            $sellerData = $this->sellerConfig->getSellerData();
            $couldLoadSellerData = $sellerData[self::STATUS] === self::SUCCESS;
            $sellerInfo = $couldLoadSellerData ? $sellerData[self::DATA] : [];

            if (!$couldLoadSellerData) {
                return [
                    self::CREDENTIALS_STATE => self::LINKED,
                    self::TYPE              => self::LINKED_FAILED_TO_LOAD,
                    self::TITLE             => $this->adminTranslations->credentialsLinkComponents[self::LINKED_TITLE],
                    self::DESCRIPTION       => $this->adminTranslations->credentialsLinkComponents[self::LINKED_DESCRIPTION],
                    self::STORE_NAME        => $this->adminTranslations->credentialsLinkComponents[self::LINKED_FAILED_TO_LOAD_STORE_NAME],
                    self::STORE_CONTACT     => $this->adminTranslations->credentialsLinkComponents[self::LINKED_FAILED_TO_LOAD_STORE_CONTACT],
                    self::LINKED_ACCOUNT    => self::ERROR,
                    self::BUTTON            => $this->adminTranslations->credentialsLinkComponents[self::FAILED_BUTTON],
                    self::MORE_INFO         => '',
                    self::LINKED_DATA       => '',
                    self::CURRENT_SITE_ID   => $this->sellerConfig->getSiteId(),
                    self::PERIOD            => ''
                ];
            }

            switch ($linkStatus) {
                case self::RECENTLY_LINKED:
                    $response = [
                        self::CREDENTIALS_STATE => self::LINKED,
                        self::TYPE              => self::RECENTLY_LINKED,
                        self::TITLE             => $this->adminTranslations->credentialsLinkComponents[self::LINKED_TITLE],
                        self::DESCRIPTION       => $this->adminTranslations->credentialsLinkComponents[self::LINKED_DESCRIPTION],
                        self::STORE_NAME        => $couldLoadSellerData ? $sellerInfo[self::NICKNAME] : '',
                        self::STORE_CONTACT     => $couldLoadSellerData ? $sellerInfo[self::EMAIL] : '',
                        self::APP_NAME          => $couldLoadSellerData ? $sellerInfo[self::APP_NAME] : '',
                        self::LINKED_ACCOUNT    => $this->adminTranslations->credentialsLinkComponents[self::LINKED_ACCOUNT],
                        self::BUTTON            => $this->adminTranslations->credentialsLinkComponents[self::LINKED_BUTTON],
                        self::MORE_INFO         => $this->adminTranslations->credentialsLinkComponents[self::LINKED_MORE_INFO],
                        self::LINKED_DATA       => $this->adminTranslations->credentialsLinkComponents[self::LINKED_DATA],
                        self::PERIOD            => '.'
                    ];
                    break;

                case self::LINK_UPDATED:
                    $response = [
                        self::CREDENTIALS_STATE => self::LINKED,
                        self::TYPE              => self::LINK_UPDATED,
                        self::TITLE             => $this->adminTranslations->credentialsLinkComponents[self::LINK_UPDATED_TITLE],
                        self::DESCRIPTION       => $this->adminTranslations->credentialsLinkComponents[self::LINK_UPDATED_DESCRIPTION],
                        self::STORE_NAME        => $couldLoadSellerData ? $sellerInfo[self::NICKNAME] : '',
                        self::STORE_CONTACT     => $couldLoadSellerData ? $sellerInfo[self::EMAIL] : '',
                        self::APP_NAME          => $couldLoadSellerData ? $sellerInfo[self::APP_NAME] : '',
                        self::LINKED_ACCOUNT    => $this->adminTranslations->credentialsLinkComponents[self::LINKED_ACCOUNT],
                        self::BUTTON            => $this->adminTranslations->credentialsLinkComponents[self::LINKED_BUTTON],
                        self::MORE_INFO         => $this->adminTranslations->credentialsLinkComponents[self::LINKED_MORE_INFO],
                        self::LINKED_DATA       => $this->adminTranslations->credentialsLinkComponents[self::LINKED_DATA],
                        self::PERIOD            => '.'
                    ];
                    break;

                case self::PREVIOUSLY_LINKED:
                    // already or previously linked
                    $response = [
                        self::CREDENTIALS_STATE => self::LINKED,
                        self::TYPE              => self::PREVIOUSLY_LINKED,
                        self::TITLE             => $this->adminTranslations->credentialsLinkComponents[self::PREVIOUSLY_LINKED_TITLE],
                        self::DESCRIPTION       => $this->adminTranslations->credentialsLinkComponents[self::PREVIOUSLY_LINKED_DESCRIPTION],
                        self::STORE_NAME        => $couldLoadSellerData ? $sellerInfo[self::NICKNAME] : '',
                        self::STORE_CONTACT     => $couldLoadSellerData ? $sellerInfo[self::EMAIL] : '',
                        self::APP_NAME          => $couldLoadSellerData ? $sellerInfo[self::APP_NAME] : '',
                        self::LINKED_ACCOUNT    => $this->adminTranslations->credentialsLinkComponents[self::LINKED_ACCOUNT],
                        self::BUTTON            => $this->adminTranslations->credentialsLinkComponents[self::LINKED_BUTTON],
                        self::MORE_INFO         => $this->adminTranslations->credentialsLinkComponents[self::LINKED_MORE_INFO],
                        self::LINKED_DATA       => $this->adminTranslations->credentialsLinkComponents[self::LINKED_DATA],
                        self::PERIOD            => '.'
                    ];
                    break;

                case self::LINKED_NO_TEST_CREDENTIALS:
                    $response = [
                        self::CREDENTIALS_STATE => self::LINKED,
                        self::TYPE              => self::LINKED_NO_TEST_CREDENTIALS,
                        self::TITLE             => $this->adminTranslations->credentialsLinkComponents[self::LINKED_TITLE],
                        self::DESCRIPTION       => $this->adminTranslations->credentialsLinkComponents[self::LINKED_DESCRIPTION],
                        self::STORE_NAME        => $couldLoadSellerData ? $sellerInfo[self::NICKNAME] : '',
                        self::STORE_CONTACT     => $couldLoadSellerData ? $sellerInfo[self::EMAIL] : '',
                        self::APP_NAME          => $couldLoadSellerData ? $sellerInfo[self::APP_NAME] : '',
                        self::LINKED_ACCOUNT    => $this->adminTranslations->credentialsLinkComponents[self::LINKED_ACCOUNT],
                        self::BUTTON            => $this->adminTranslations->credentialsLinkComponents[self::LINKED_BUTTON],
                        self::MORE_INFO         => $this->adminTranslations->credentialsLinkComponents[self::LINKED_MORE_INFO],
                        self::LINKED_DATA       => $this->adminTranslations->credentialsLinkComponents[self::LINKED_DATA],
                        self::PERIOD            => '.'
                    ];
                    break;
            }

            $response[self::CURRENT_SITE_ID] = $this->sellerConfig->getSiteId();

            return $response;
        }

        $notLinkedStatuses = [
            self::NOT_LINKED_FAILED,
            self::EXPIRED,
            self::COULD_NOT_VALIDATE_LINK,
            self::UNAUTHORIZED_ACCESS_TOKEN,
        ];

        if (in_array($linkStatus, $notLinkedStatuses)) {
            switch ($linkStatus) {
                case self::NOT_LINKED_FAILED:
                    $response = [
                        self::CREDENTIALS_STATE => self::NOT_LINKED,
                        self::TYPE              => self::FAILED,
                        self::TITLE             => $this->adminTranslations->credentialsLinkComponents[self::FAILED_TITLE],
                        self::DESCRIPTION       => $this->adminTranslations->credentialsLinkComponents[self::FAILED_DESCRIPTION],
                        self::BUTTON            => $this->adminTranslations->credentialsLinkComponents[self::FAILED_BUTTON]
                    ];
                    break;

                case self::EXPIRED:
                    $response = [
                        self::CREDENTIALS_STATE => self::NOT_LINKED,
                        self::TYPE              => self::UPDATE,
                        self::TITLE             => $this->adminTranslations->credentialsLinkComponents[self::UPDATE_TITLE],
                        self::DESCRIPTION       => $this->adminTranslations->credentialsLinkComponents[self::UPDATE_DESCRIPTION],
                        self::BUTTON            => $this->adminTranslations->credentialsLinkComponents[self::UPDATE_BUTTON]
                    ];
                    break;

                case self::COULD_NOT_VALIDATE_LINK:
                    $response = [
                        self::CREDENTIALS_STATE => self::NOT_LINKED,
                        self::TYPE              => self::FAILED,
                        self::TITLE             => $this->adminTranslations->credentialsLinkComponents[self::COULD_NOT_VALIDATE_LINK_TITLE],
                        self::DESCRIPTION       => $this->adminTranslations->credentialsLinkComponents[self::COULD_NOT_VALIDATE_LINK_DESCRIPTION],
                        self::BUTTON            => $this->adminTranslations->credentialsLinkComponents[self::FAILED_BUTTON]
                    ];
                    break;

                case self::UNAUTHORIZED_ACCESS_TOKEN:
                    $response = [
                        self::CREDENTIALS_STATE => self::NOT_LINKED,
                        self::TYPE              => self::UNAUTHORIZED,
                        self::TITLE             => $this->adminTranslations->credentialsLinkComponents[self::COULD_NOT_VALIDATE_LINK_TITLE],
                        self::DESCRIPTION       => $this->adminTranslations->credentialsLinkComponents[self::COULD_NOT_VALIDATE_LINK_DESCRIPTION],
                        self::BUTTON            => $this->adminTranslations->credentialsLinkComponents[self::FAILED_BUTTON],
                        self::SECONDARY_BUTTON  => $this->adminTranslations->credentialsLinkComponents[self::UPDATE_BUTTON]
                    ];
                    break;
            }

            return $response;
        }

        return [
            self::CREDENTIALS_STATE => self::NOT_LINKED,
            self::TYPE              => 'initial',
        ];
    }
}
