# Установка
Выполнить ```docker-compose up -d```

Зайти в контейнер:
```docker-compose exec app sh```

Скопировать .env.example в .env
```cp .env.example .env```

Настроить .env файл

# Важно
Для работы необходимо провести миграцию бд

Заходим в контейнер:
```docker-compose exec app sh```

Запускаем миграцию
```php artisan migrate```

# Сборка и работа с vue
Исходники vue находятся в директории widget/installer/src

Используя npm или yarn запустить 
```yarn install``` 
или
```npm install```

После вненсений изменений сборка виджета совершается путем
```npm run build```
или
```yarn build```

Собранный виджет можно найти в директории ./app/public/widget

url для доступа к виджету APP_URL/widget/ -- например APP_URL/widget/installer.js

# Scritp js и работа с обычным функционалом виджета

Обычная часть виджета находится внутри  widget/installer/src/widget

Инициализация vue происходит путем вызова
```self.utils.initializeVue('parent_node_selector', 'condition if needed')```

Twig  шаблоны рендерятся путем вызова метода 
```self.utils.loadTemplates(['array of templates'], callback)```

# Пример инициализации vue внутри вкладки настройки

```
settings() {
        self.utils.loadTemplates(['tab_item', 'tab_content'], function () {

          const tabParams = {
            id: 'setting',
            text: 'Настройки'
          };
          const tabHtml = self.templates['tab_item'].render({
            tab: tabParams
          });
          $modal.find('.view-integration-modal__tabs .tabs').append(tabHtml);
          const $modalBody = $modal.find('.modal-body');
          $modalBody.width($modalBody.width() + $('#setting').width() + 40);
          $modalBody.trigger('modal:centrify');

          const tabContentHtml = self.templates['tab_content'].render({
            tab: tabParams
          });
          
          $modal.find('.widget-settings__desc-space').before(tabContentHtml);
          self.utils.initializeVue('.view-integration-modal__' + tabParams.id,);

        })
      }
````