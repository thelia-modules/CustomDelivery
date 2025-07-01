<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/


namespace CustomDelivery\Controller;

use CustomDelivery\CustomDelivery;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;
use CustomDelivery\Service\CustomDeliveryService;


/**
 * Class BackController
 * @package CustomDelivery\Controller
 * @author Julien Chanséaume <julien@thelia.net>
 */

class BackController extends BaseAdminController
{
    protected CustomDeliveryService $customDeliveryService;

    public function __construct(CustomDeliveryService $customDeliveryService)
    {
        $this->customDeliveryService = $customDeliveryService;
    }

    #[Route('/admin/module/customdelivery/save', name: 'customdelivery.admin.update', methods: ['POST'])]
    public function saveAction(Request $request): Response
    {
        $this->checkXmlHttpRequest();

        $data = [
            'id' => (int) $request->get('id', 0),
            'area' => (int) $request->get('area', 0),
            'priceMax' => $request->get('priceMax', 0),
            'weightMax' => $request->get('weightMax', 0),
            'price' => $request->get('price', 0),
        ];

        $result = $this->customDeliveryService->saveSlice(
            $data
        );

        $responseData = [
            'success' => $result['success'],
            'message' => $result['messages'],
            'slice' => $result['slice'] ? $result['slice']->toArray(TableMap::TYPE_STUDLYPHPNAME) : null,
        ];

        return $this->jsonResponse(json_encode($responseData));
    }

    #[Route('/admin/module/customdelivery/delete', name: 'customdelivery.admin.delete', methods: ['POST'])]
    public function deleteAction(Request $request): Response
    {
        $authResponse = $this->checkAuth([], ['customdelivery'], AccessManager::DELETE);
        if ($authResponse !== null) {
            return $authResponse;
        }

        $this->checkXmlHttpRequest();

        $id = (int) $request->get('id', 0);

        $result = $this->customDeliveryService->deleteSlice($id);

        $responseData = [
            'success' => $result['success'],
            'message' => $result['messages'],
            'slice' => null,
        ];

        return $this->jsonResponse(json_encode($responseData));
    }

    #[Route('/admin/module/customdelivery/configuration', name: 'customdelivery.admin.configuration', methods: ['POST'])]
    public function saveConfigurationAction(): Response
    {
        $authResponse = $this->checkAuth([AdminResources::MODULE], ['customdelivery'], AccessManager::UPDATE);
        if ($authResponse !== null) {
            return $authResponse;
        }

        $form = $this->createForm('customdelivery.configuration.form');
        $message = "";

        try {
            $vform = $this->validateForm($form);
            $data = $vform->getData();

            ConfigQuery::write(CustomDelivery::CONFIG_TRACKING_URL, $data['url']);
            ConfigQuery::write(CustomDelivery::CONFIG_PICKING_METHOD, $data['method']);
            ConfigQuery::write(CustomDelivery::CONFIG_TAX_RULE_ID, $data['tax']);
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        if ($message !== "") {
            $form->setErrorMessage($message);
            $this->getParserContext()
                ->addForm($form)
                ->setGeneralError($message);

            return $this->render(
                "module-configure",
                ["module_code" => CustomDelivery::getModuleCode()]
            );
        }

        return $this->generateRedirect(
            URL::getInstance()->absoluteUrl("/admin/module/" . CustomDelivery::getModuleCode())
        );
    }
}
