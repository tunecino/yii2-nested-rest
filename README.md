# yii2-nested-rest

[![Packagist Version](https://img.shields.io/packagist/v/tunecino/yii2-nested-rest.svg?style=flat-square)](https://packagist.org/packages/tunecino/yii2-nested-rest)
[![Total Downloads](https://img.shields.io/packagist/dt/tunecino/yii2-nested-rest.svg?style=flat-square)](https://packagist.org/packages/tunecino/yii2-nested-rest)

Adds nested resources routing support along with related actions and relationship handlers to the [Yii RESTful API framework](http://www.yiiframework.com/doc-2.0/guide-rest-quick-start.html).

**Note: This is an alpha release. Use it with precautions.**

## How It Works
This extension doesn't replace any of the built-in REST components. It is about a collection of helper actions and a custom `UrlRule` class designed to be used along with the default one:

```php
'rules' => [
    [
        // Yii defaults UrlRule class
        'class' => 'yii\rest\UrlRule',
        'controller' => ['team','player','skill'],
    ],
    [
        // The custom UrlRule class
        'class' => 'tunecino\nestedrest\UrlRule',
        'modelClass' => 'app\models\Team',
        'relations' => ['players'],
    ],
    [
        'class' => 'tunecino\nestedrest\UrlRule',
        'modelClass' => 'app\models\Player',
        'relations' => ['team','skills'],
    ],
]
```

To understand how it works, I think it would be better explained within a practical example: 

If within the previous configurations we expect `team` and `player` to share a *one-to-many* relationship while  `player` and `skill` shares a *many-to-many* relation within a junction table and having an extra column called `level` in that junction table then this extension may help achieving the following HTTP requests:
     
```bash
# get the players 2, 3 and 4 from team 1
GET /teams/1/players/2,3,4

# list all skills of player 5
GET /players/5/skills

# put the players 5 and 6 in team 1
PUT /teams/1/players/5,6

# create a new player and put him in team 1
POST /teams/1/players
{name: 'Didier Drogba', position: 'FC'}

# create a new skill called 'dribble' and assign it to player 9
# with a related level of 10 ('level' should be stored in the junction table)
POST /players/9/skills
{name: 'dribble', level: 10}

# remove skill 3 from player 2
DELETE /players/2/skills/3

# get all players out of team 2
DELETE /teams/2/players
```

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```bash
$ composer require tunecino/yii2-nested-rest
```

or add

```
"tunecino/yii2-nested-rest": "*"
```

to the `require` section of your `composer.json` file.



## Configuration

By default, all the properties used by the custom UrlRule class in this extension will be used to generate multiple instances of the built-in [yii\rest\UrlRule](http://www.yiiframework.com/doc-2.0/yii-rest-urlrule.html) so basically both classes are sharing similar configurations. 

This is kind of template with all the possible configurations that may be set to the UrlManager in the app config file:
```php
'rules' => [
    [
        /**
         * the custom UrlRule
         */
        'class' => 'tunecino\nestedrest\UrlRule', /* required */
        /**
         * the model class name
         */
        'modelClass' => 'app\models\Player', /* required */
         /**
         * relations names to be nested with this model
         * they should be already defined in the model class.
         */
        'relations' => ['team','skills'], /* required */
        /**
         * used to generate the 'prefix'.
         * default: the model name pluralized
         */
        'resourceName' => 'players', /* optional */
        /**
         * also used with 'prefix'. is the expected foreign key.
         * default: $model_name . '_id'
         */
        'linkAttribute' => 'player_id', /* optional */
        /**
         *  building related rules using 'controller => ['teams' => 'v1/team']' 
         *  instead of 'controller => ['team']'
         */
        'modulePrefix' => 'v1', /* optional */
        /**
         * the default list of tokens that should be replaced for each pattern
         * in case you have a reason to replace them.
        */
        'tokens' = [
            '{id}' => '<id:\\d[\\d,]*>',
            '{IDs}' => '<IDs:\\d[\\d,]*>',
        ], /* optional */
        /**
         *  the default list of patterns. they may all be overridden here
         *  or just edited within $only, $except and $extraPatterns properties
         */
        'patterns' => [ 
            'GET,HEAD {IDs}' => 'nested-view',
            'GET,HEAD' => 'nested-index',
            'POST' => 'nested-create',
            'PUT {IDs}' => 'nested-link',
            'DELETE {Ids}' => 'nested-unlink',
            'DELETE' => 'nested-unlink-all',
            '{id}' => 'options',
            '' => 'options',
        ], /* optional */
        /**
         *  list of acceptable actions.
         */
        'only' => [], /* optional */
        /**
         *  actions that should be excluded.
         */
        'except' => [], /* optional */
        /**
         *  supporting extra actions in addition to those listed in $patterns.
         */
        'extraPatterns' => [] /* optional */
    ],
]
```
As you may notice; by default; `$patterns` is pointing to 6 new actions different from the basic CRUD actions attached to the [ActiveController](http://www.yiiframework.com/doc-2.0/yii-rest-activecontroller.html) class. Those are the helper actions included in this extension and you will need to manually declare them whenever needed inside your controllers or inside a `BaseController` from which all others should extend. Also note that by default we are expecting an [OptionsAction](http://www.yiiframework.com/doc-2.0/yii-rest-optionsaction.html) attached to the related controller. That should be the case with any controller extending [ActiveController](http://www.yiiframework.com/doc-2.0/yii-rest-activecontroller.html) or its child, otherwise you should also implement `\yii\rest\OptionsAction`.

The following is an example of a full implementation within the [controller::actions()](http://www.yiiframework.com/doc-2.0/yii-rest-activecontroller.html#actions%28%29-detail) function:

```php
public function actions() 
{
    $actions = parent::actions(); 
       
    $actions['nested-index'] = [
        'class' => 'tunecino\nestedrest\IndexAction', /* required */
        'modelClass' => $this->modelClass, /* required */
        'checkAccess' => [$this, 'checkAccess'], /* optional */
    ];

    $actions['nested-view'] = [
        'class' => 'tunecino\nestedrest\ViewAction', /* required */
        'modelClass' => $this->modelClass, /* required */
        'checkAccess' => [$this, 'checkAccess'], /* optional */
    ];

    $actions['nested-create'] = [
        'class' => 'tunecino\nestedrest\CreateAction', /* required */
        'modelClass' => $this->modelClass, /* required */
        'checkAccess' => [$this, 'checkAccess'], /* optional */
        /**
         * the scenario to be assigned to the new model before it is validated and saved.
         */
        'scenario' => 'default', /* optional */
        /**
         * the scenario to be assigned to the model class responsible 
         * of handling the data stored in the juction table.
         */
        'viaScenario' => 'default', /* optional */
        /**
         * expect junction table related data to be wrapped in a sub object key in the body request.
         * In the example we gave above we would need to do :
         * POST {name: 'dribble', related: {level: 10}}
         * instead of {name: 'dribble', level: 10}
         */
        'viaWrapper' => 'related' /* optional */
    ];

    $actions['nested-link'] = [
        'class' => 'tunecino\nestedrest\LinkAction', /* required */
        'modelClass' => $this->modelClass, /* required */
        'checkAccess' => [$this, 'checkAccess'], /* optional */
        /**
         * the scenario to be assigned to the model class responsible 
         * of handling the data stored in the juction table.
         */
        'viaScenario' => 'default', /* optional */
    ];

    $actions['nested-unlink'] = [
        'class' => 'tunecino\nestedrest\UnlinkAction', /* required */
        'modelClass' => $this->modelClass, /* required */
        'checkAccess' => [$this, 'checkAccess'], /* optional */
    ];

    $actions['nested-unlink-all'] = [
        'class' => 'tunecino\nestedrest\UnlinkAllAction', /* required */
        'modelClass' => $this->modelClass, /* required */
        'checkAccess' => [$this, 'checkAccess'], /* optional */
    ];
    
    return $actions;
}
```

## What you need to know

***1.*** This doesn't support composite keys. In fact one of my main concerns when building this extension was to figure out a clean alternative to not have to build resources for composite keys related models like the ones mapping a junction table. check the example provided in section ***7.*** for more details.

***2.*** When defining relation names in the config file they should match the method names implemented inside your model *(see [Declaring Relations](http://www.yiiframework.com/doc-2.0/guide-db-active-record.html#declaring-relations) section in the Yii guide for more details)*. 
This extension will do the check and will throw an *InvalidConfigException* if they don't match but for performance reasons *(check [this](http://www.yiiframework.com/doc-2.0/guide-db-active-record.html#declaring-relations))* and because it make no sense to keep doing the same verification with each request when you already did correctly set a list of relations, this extension won't do that DB schema parsing anymore when the application is in *production* mode. in other words verification is made only when`YII_DEBUG` is true.

***3.*** When it comes to linking *many-to-many* relations with extra columns in a junction table it is highly recommended to use [via()](http://www.yiiframework.com/doc-2.0/yii-db-activerelationtrait.html#via%28%29-detail) instead of [viaTable()](http://www.yiiframework.com/doc-2.0/yii-db-activequery.html#viaTable%28%29-detail) so the intermediate class can be used by this extension to validate related attributes instead of using [link()](http://www.yiiframework.com/doc-2.0/yii-db-baseactiverecord.html#link%28%29-detail) and saving data without performing the appropriate validations. Refer to the [Relations via a Junction Table](http://www.yiiframework.com/doc-2.0/guide-db-active-record.html#junction-table) section in the Yii guide for more details.

***4.*** When you do:
```bash
POST /players/9/skills
{name: 'dribble', level: 10}
```
and the 'name' attribute is supposed to be loaded and saved along with the new created model while 'level' should be added in a related junction table. Then you should know this: 

 - If relation between both models is defined within [via()](http://www.yiiframework.com/doc-2.0/yii-db-activerelationtrait.html#via%28%29-detail)  , `Yii::$app->request->bodyParams` will be populated to to both models using the [load()](http://www.yiiframework.com/doc-2.0/yii-base-model.html#load()-detail) method:
   
   ```php 
   // you can also assign scenarios when attaching actions to both instances. see configuration section
   $model->load($bodyParams); 
   $viaModel->load($bodyParams); 
   ```

 - If relation is defined within [viaTable()](http://www.yiiframework.com/doc-2.0/yii-db-activequery.html#viaTable%28%29-detail) instead the script will try to do some guessing.

So when unexpected results happens or when attribute names are similar in model class and junction related class, it would be recommended to set the `viaWrapper` property. See the 'nested-create' action in the [configuration](#configuration) section for more details.

***5.*** When unlinking data, if the relation type between both models is *many_to_many* related row in the junction table will be removed. Otherwise the concerned foreign key attribute will be set to NULL in its related column in database.

***6.*** When a successful linking or unlinking request happens, a `204` response should be expected while a `304` response should tell that no change has been made like when asking to link two already linked models.
When you try to link 2 models sharing a `many_to_many` relationship and both models are already linked no extra row will be added to related junction table. If the `bodyRequest` is empty you'll get a `304` response otherwise the `bodyRequest` content will be used to update the extra attributes found in the junction table and you'll get a `204` headers response.

***7.*** When performing any HTTP request; lets say as example `GET /players/9/skills/2`; The custom `UrlRule` will redirect it by default to the route `skill/nested-view` *(or other depending on your patterns)* with those 4 extra attributes added to `Yii::$app->request->queryParams`:

```php
relativeClass = 'app/models/player'; // the class name of the relative model
relationName  = 'skills'; // the one you did set in rules configuration.
linkAttribute = 'player_id'; // the foreign key attribute name.
player_id     = 9; // the foreign key attribute and its value
```
Those may be useful when building your own actions or doing extra things like for example if we add the following inside `app/models/skill` :

```php
// junction table related method. usually auto generated by gii.
public function getSkillHasPlayers()
{
    return $this->hasMany(SkillHasPlayer::className(), ['skill_id' => 'id']);
}

protected function getSharedData()
{
    $params = Yii::$app->request->queryParams;
    $player_id = empty($params['player_id']) ? null : $params['player_id'];

    return ($player_id) ? $this->getSkillHasPlayers()
                             ->where(['player_id' => $player_id ])
                             ->select('level')
                             ->one() : null;
}

public function fields()
{
    $fields = parent::fields();

    if (!empty(Yii::$app->request->queryParams['player_id'])) {
        $fields['_shared'] = 'sharedData';
    }

    return $fields;
}
```

a request like `GET /players/9/skills` or `GET /players/9/skills/2` will also output the related data between both models that is stored in the related junction table:

```bash
GET /players/9/skills/2
# outputs:
{
  "id": 2,
  "name": "dribble",
  "_shared": {
    "level": 10
  }
}
```
