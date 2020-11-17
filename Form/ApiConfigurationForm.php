<?php

namespace DpdLabel\Form;


use DpdLabel\DpdLabel;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class ApiConfigurationForm extends BaseForm
{
    protected function buildForm()
    {
        $data = DpdLabel::getApiConfig();

        $this->formBuilder
            ->add("user_id",
                TextType::class,
                [
                    "required" => true,
                    "data" => $data['userId'],
                    "label" => Translator::getInstance()->trans("User id", [], DpdLabel::DOMAIN_NAME),
                    "label_attr" => [
                        "for" => "user_id",
                    ],
                ]
            )
            ->add("password",
                TextType::class,
                [
                    "required" => true,
                    "data" => $data['password'],
                    "label" => Translator::getInstance()->trans("Password", [], DpdLabel::DOMAIN_NAME),
                    "label_attr" => [
                        "for" => "user_id",
                    ],
                ]
            )
            ->add("center_number",
                TextType::class,
                [
                    "required" => true,
                    "data" => $data['center_number'],
                    "label" => Translator::getInstance()->trans("Center number", [], DpdLabel::DOMAIN_NAME),
                    "label_attr" => [
                        "for" => "center_number",
                    ],
                ]
            )
            ->add("customer_number",
                TextType::class,
                [
                    "required" => true,
                    "data" => $data['customer_number'],
                    "label" => Translator::getInstance()->trans("Customer number", [], DpdLabel::DOMAIN_NAME),
                    "label_attr" => [
                        "for" => "customer_number",
                    ],
                ]
            )
            ->add("isTest",
                CheckboxType::class,
                [
                    'required' => false,
                    "data" => $data['isTest'] === 1,
                    "label" => Translator::getInstance()->trans("Test mode", [], DpdLabel::DOMAIN_NAME),
                    "label_attr" => [
                        "for" => "isTest",
                    ],
                ]
            )
            /** Shipper Informations */
            ->add('shipper_name',
                TextType::class,
                [
                    'required' => true,
                    'data' => $data['shipperName'],
                    'label' => Translator::getInstance()->trans("Company name", [], DpdLabel::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'shipper_name',
                    ],
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans("Dupont & co")
                    ],
                ]
            )
            ->add('shipper_address1',
                TextType::class,
                [
                    'required' => true,
                    'data' => $data['shipperAddress1'],
                    'label' => Translator::getInstance()->trans("Address", [], DpdLabel::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'shipper_address1',
                    ],
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans("Les Gardelles")
                    ],
                ]
            )
            ->add('shipper_country',
                TextType::class,
                [
                    'required' => true,
                    'data' => $data['shipperCountry'],
                    'label' => Translator::getInstance()->trans("Country (ISO ALPHA-2 format)", [], DpdLabel::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'shipper_country',
                    ],
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans("FR")
                    ],
                ]
            )
            ->add('shipper_city',
                TextType::class,
                [
                    'required' => true,
                    'data' => $data['shipperCity'],
                    'label' => Translator::getInstance()->trans("City", [], DpdLabel::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'shipper_city',
                    ],
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans("Paris")
                    ],
                ]
            )
            ->add('shipper_zip_code',
                TextType::class,
                [
                    'required' => true,
                    'data' => $data['shipperZipCode'],
                    'label' => Translator::getInstance()->trans("ZIP code", [], DpdLabel::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'shipper_zip_code',
                    ],
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans("93000")
                    ],
                ]
            )
            ->add('shipper_phone',
                TextType::class,
                [
                    'required' => true,
                    'data' => $data['shipperPhone'],
                    'label' => Translator::getInstance()->trans("Phone", [], DpdLabel::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'shipper_phone',
                    ],
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans("0142080910")
                    ],
                ]
            )
            ->add('shipper_fax',
                TextType::class,
                [
                    'required' => false,
                    'data' => $data['shipperFax'],
                    'label' => Translator::getInstance()->trans("Fax number", [], DpdLabel::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'shipper_fax',
                    ],
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans("")
                    ],
                ]
            );
    }

    public function getName()
    {
        return "dpdlabel-api-config-form";
    }
}