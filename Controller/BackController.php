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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\Base\CustomerQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\MetaData;
use Thelia\Model\MetaDataQuery;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Tools\URL;
use VirtualProductGereso\Form\ConfigurationForm;
use VirtualProductGereso\Model\VirtualOrderProduct;
use VirtualProductGereso\Model\VirtualOrderProductQuery;
use VirtualProductGereso\VirtualProductGereso;

/**
 * Class BackController
 * @package CustomDelivery\Controller
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class BackController extends BaseAdminController
{
    // protected $currentRouter = 'router.customdelivery';

    public function updateAction()
    {
        $response = $this->checkAuth([AdminResources::MODULE], ['virtualproductgereso'], AccessManager::UPDATE);
        if (null !== $response) {
            return $response;
        }

        $this->checkXmlHttpRequest();

        $modificationForm = $this->createForm('vpg.update.form', 'form');

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($modificationForm, "POST");
            $data = $form->getData();

            $virtualOrderProduct = VirtualOrderProductQuery::create()->findPk($data['update_id']);
            $virtualOrderProduct
                ->setDownload($data['update_download'])
                ->setExpire(
                    \DateTime::createFromFormat('Y-m-d', $data['update_expire'])
                )
                ->save()
            ;

            return $this->generateSuccessRedirect($modificationForm);
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        if (false !== $error_msg) {
            $this->setupFormErrorContext(
                $this->getTranslator()->trans("Virtual product offer", [], VirtualProductGereso::getModuleCode()),
                $error_msg,
                $modificationForm,
                $ex
            );

            // At this point, the form has error, and should be redisplayed.
            return $this->listAction();
        }

        return $this->generateRedirectFromRoute('admin.vpg.list');
    }

    /**
     * Save module configuration
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function saveConfigurationAction()
    {
        $response = $this->checkAuth([AdminResources::MODULE], ['customdelivery'], AccessManager::UPDATE);

        if (null !== $response) {
            return $response;
        }

        $form = new ConfigurationForm($this->getRequest());
        $message = "";

        $response = null;

        try {
            $vform = $this->validateForm($form);
            $data = $vform->getData();

            ConfigQuery::write(
                CustomDelivery::CONFIG_TRACKING_URL,
                $data['url']
            );
            ConfigQuery::write(
                CustomDelivery::CONFIG_PICKING_METHOD,
                $data['method']
            );
            ConfigQuery::write(
                CustomDelivery::CONFIG_TAX_RULE_ID,
                $data['tax']
            );
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }
        if ($message) {
            $form->setErrorMessage($message);
            $this->getParserContext()->addForm($form);
            $this->getParserContext()->setGeneralError($message);

            return $this->render(
                "module-configure",
                ["module_code" => VirtualProductGereso::getModuleCode()]
            );
        }

        return RedirectResponse::create(
            URL::getInstance()->absoluteUrl("/admin/module/" . VirtualProductGereso::getModuleCode())
        );
    }

    public function saveRelationsAction()
    {
        $message = '';
        return $this->jsonResponse(json_encode($message));
    }
}
