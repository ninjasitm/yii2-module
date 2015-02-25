<?php

namespace nitm\controllers;

class HttpResponseController extends DefaultController
{
    public function actionIndex()
    {
        return $this->render('index');
    }

}
