<?php

namespace app\models;

use Yii;
use yii\base\Model;

class LoginForm extends Model
{
    public string $username = '';
    public string $password = '';
    public bool   $rememberMe = true;

    private ?User $_user = null;

    public function rules(): array
    {
        return [
            [['username', 'password'], 'required'],
            [['rememberMe'], 'boolean'],
            [['password'], 'validatePassword'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'username'   => 'Username',
            'password'   => 'Password',
            'rememberMe' => 'Keep me signed in',
        ];
    }

    public function validatePassword($attribute): void
    {
        if ($this->hasErrors()) return;

        $user = $this->getUser();
        if (!$user || !$user->validatePassword($this->password)) {
            $this->addError($attribute, 'Incorrect username or password.');
        }
    }

    public function login(): bool
    {
        if (!$this->validate()) return false;

        $user = $this->getUser();
        $duration = $this->rememberMe ? 30 * 24 * 3600 : 0;

        if (Yii::$app->user->login($user, $duration)) {
            $user->recordLogin();
            return true;
        }

        return false;
    }

    private function getUser(): ?User
    {
        if ($this->_user === null) {
            $this->_user = User::findByUsername($this->username);
        }
        return $this->_user;
    }
}