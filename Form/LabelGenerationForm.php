<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 03/11/2020
 * Time: 16:42
 */

namespace DpdLabel\Form;


use DpdLabel\DpdLabel;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
            )
            ->add(
                'new_status',
                ChoiceType::class, [
                    'label' => Translator::getInstance()->trans('Order status after export'),
                    'choices' => [
                        Translator::getInstance()->trans('Do not change', [], DpdLabel::DOMAIN_NAME) => 'nochange',
                        Translator::getInstance()->trans('Set orders status as processing', [], DpdLabel::DOMAIN_NAME) => 'processing',
                        Translator::getInstance()->trans('Set orders status as sent', [], DpdLabel::DOMAIN_NAME) => 'sent',
                    ],
                    'required' => false,
                    'expanded' => true,
                    'multiple' => false,
                    'data' => DpdLabel::getConfigValue('new_status', 'nochange'),
                ]
            );
    }

    public static function getName()
    {
        return "dpdlabel_label_generation_form";
    }

}
