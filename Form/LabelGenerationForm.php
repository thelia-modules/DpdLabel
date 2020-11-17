<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 03/11/2020
 * Time: 16:42
 */

namespace DpdLabel\Form;


use DpdLabel\DpdLabel;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class LabelGenerationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'weight',
                TextType::class,
                [
                    "required" => true,
                    "label" => Translator::getInstance()->trans("Weight (kg)", [], DpdLabel::DOMAIN_NAME),
                    "label_attr" => [
                        "for" => "weight",
                    ],
                ]
            );
    }

    public function getName()
    {
        return "dpdlabel-label-generation-form";
    }

}