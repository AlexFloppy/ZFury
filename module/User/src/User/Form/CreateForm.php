<?php

namespace User\Form;

use Zend\Form\Form;

class CreateForm extends Form
{
    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);
        $this->setAttribute('method', 'post');

        $this->add(
            [
                'name' => 'email',
                'type' => 'text',
                'options' => [
                    'min' => 3,
                    'max' => 25,
                ],
                'attributes' => [
                    'class' => 'form-control',
                    'required' => 'true',
                ]
            ]
        );

        $this->add(
            [
                'name' => 'displayName',
                'type' => 'text',
                'options' => [
                    'min' => 3,
                    'max' => 25,
                    'label' => 'Name'
                ],
                'attributes' => [
                    'class' => 'form-control',
                    'required' => 'true',
                ]
            ]
        );

        $this->add(
            [
                'name' => 'password',
                'type' => 'Password',
                'options' => [
                    'min' => 3,
                    'max' => 25
                ],
                'attributes' => [
                    'class' => 'form-control',
                    'required' => 'true',
                ]
            ]
        );

        $this->add(
            [
                'name' => 'repeat-password',
                'type' => 'Password',
                'options' => [
                    'min' => 3,
                    'max' => 25,
                    'label' => 'Repeat password'
                ],
                'attributes' => [
                    'class' => 'form-control',
                    'required' => 'true',
                ]
            ]
        );

        $this->add(
            [
                'name' => 'role',
                'type' => 'select',
                'options' => [
                    'value_options' => [
                        'user' => 'User',
                        'admin' => 'Admin',
                    ]
                ],
                'attributes' => [
                    'required' => 'true',
                    'class' => 'form-control',
                ]
            ]
        );

        $this->add(
            [
                'name' => 'status',
                'type' => 'select',
                'options' => [
                    'required' => 'false',
                    'value_options' => [
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'unconfirmed' => 'Unconfirmed'
                    ]
                ],
                'attributes' => [
                    'required' => 'true',
                    'class' => 'form-control'
                ]
            ]
        );
    }
}