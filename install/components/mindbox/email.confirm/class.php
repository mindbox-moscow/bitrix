<?php
/**
 * Created by @copyright QSOFT.
 */

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\UserTable;
use Mindbox\Ajax;
use Mindbox\DTO\V3\Requests\CustomerRequestDTO;
use Mindbox\DTO\V3\Requests\SmsConfirmationRequestDTO;
use Mindbox\Exceptions\MindboxClientException;
use Mindbox\Exceptions\MindboxException;
use Mindbox\Options;
use Mindbox\Helper;
use Mindbox\DTO\DTO;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

class EmailConfirm extends CBitrixComponent implements Controllerable
{
    protected $actions = [
        'resend'
    ];

    private $userInfo;

    private $mindbox;

    public function __construct(CBitrixComponent $component = null)
    {
        parent::__construct($component);

        try {
            if(!Loader::includeModule('mindbox.marketing')) {
                ShowError(GetMessage('MB_EC_MODULE_NOT_INCLUDED', ['#MODULE#' => 'mindbox.marketing']));
                return;
            }
        } catch (LoaderException $e) {
            ShowError(GetMessage('MB_EC_MODULE_NOT_INCLUDED', ['#MODULE#' => 'mindbox.marketing']));
            return;
        }

        $this->userInfo = $this->getUser();
        if (!$this->userInfo['UF_EMAIL_CONFIRMED'] && !empty($this->userInfo['EMAIL'])) {
            $this->mindbox = Options::getConfig();
        }
    }

    public function executeComponent()
    {

        if (!$this->userInfo['UF_EMAIL_CONFIRMED'] && !empty($this->userInfo['EMAIL'])) {
            $this->checkEmailConfirm();
        }
        $this->arResult['USER_INFO']['UF_EMAIL_CONFIRMED'] = $this->userInfo['UF_EMAIL_CONFIRMED'];
        $this->arResult['USER_INFO']['EMAIL'] = $this->userInfo['EMAIL'];
        $this->includeComponentTemplate();
    }


    public function configureActions()
    {
        return Ajax::configureActions($this->actions);
    }

    public function checkEmailConfirm()
    {
        global $USER;

        if(!$this->getMindboxId()) {
            return;
        }

            $request = $this->mindbox->getClientV3()->prepareRequest('POST',
                Options::getOperationName('getCustomerInfo'),
                new DTO(['customer' => ['ids' => ['mindboxId' => $this->getMindboxId()]]]));

            try {
                $response = $request->sendRequest();
            } catch (MindboxClientException $e) {
                return;
            }
            $customer = $response->getResult()->getCustomer();
            if ($customer && $customer->getProcessingStatus() === 'Found') {
                if (!empty($customer->getIsEmailConfirmed()) && empty($customer->getPendingEmail())) {
                    $USER->Update($this->userInfo['ID'], ['UF_EMAIL_CONFIRMED' => '1']);
                }
            }

    }

    public function resendAction()
    {
        if (!$this->mindbox) {
            return Ajax::errorResponse(GetMessage('MB_EC_BAD_MODULE_SETTING'));
        }
        $customer = new CustomerRequestDTO(['ids' => ['mindboxId' => $this->getMindboxId()]]);
        try {
            $this->mindbox->customer()->resendConfirmationCode($customer,
                Options::getOperationName('resendEmailConfirm'))->sendRequest();
        } catch (MindboxClientException $e) {
            return Ajax::errorResponse($e);
        }
    }

    protected function getUser()
    {
        global $USER;

        $rsUser = UserTable::getList(
            [
                'select' => ['EMAIL', 'UF_MINDBOX_ID', 'ID', 'UF_EMAIL_CONFIRMED'],
                'filter' => ['ID' => $USER->GetID()]
            ]
        )->fetch();

        return $rsUser;
    }

    private function getMindboxId()
    {
        global $USER;

        return Helper::getMindboxId($USER->GetID());
    }
}