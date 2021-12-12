define([
  './app.js',
  'jquery',
  'lib/components/base/modal',
  'underscore',
  'lib/components/base/confirm',
  './lib/Constants/index.js'
], function (App, $, Modal, _, Confirm, Constants) {
  return function () {
    const $body = $('body');
    const self = this;
    const system = self.system();
    const ai_widget_name = '';
    const ai_widget_code = '';
    let urlAPI = Constants.urlApi;
    const error_msg = 'Произошла ошибка на стороне сервера! Обратитесь в службу технической поддержки виджета.';
    let FIELD_ID = {}
    const FILE_FORMATS = [
      {
        id: 'docx',
        name: 'Word'
      },
      {
        id: 'pdf',
        name: 'PDF'
      },
      {
        id: 'xlsx',
        name: 'Excel'
      },
    ]
    self.templates = {};

    self.base_path = urlAPI + '/widget';
    self.base_api_path = urlAPI;
    self.hasSettingsTab = true;
    self.tabItemTemplate = 'tab_item';
    self.tabContentTemplate = 'tab_content';
    self.settingsTemplatesArray = [self.tabItemTemplate, self.tabContentTemplate, 'order_form'];
    self.logs = [];
    // globals
    self._delivery_points = {};
    self._doc_number = '';
    self._id = '';
    self._current_doc_id = '';
    self._validate_company_errors = [];

    //Утилиты
    self.utils = {
      //
      amoDateStringToTimestamp(val, with_time = false) {
        if (with_time && !val.match(/^(\d{2}\.){2}\d{4} \d{2}:\d{2}$/gm)) {
          return null;
        }
        if (!with_time && !val.match(/^(\d{2}\.){2}\d{4}$/gm)) {
          return null;
        }
        const date = val.split(' ')[0];
        const time = val.split(' ')[1];

        const d = date.split('.')[0];
        const m = +date.split('.')[1] - 1;
        const Y = date.split('.')[2];
        let H, i;
        if (with_time) {
          H = +time.split(':')[0];
          i = +time.split(':')[1];
        } else {
          H = 12;
          i = 0;
        }
        // смещение UTC таймзоны относительно локальной (для Москвы = -180)
        // const timezone_offset = new Date().getTimezoneOffset();
        // // смещение плюсовое или минусовое
        // if (timezone_offset > 0) {
        //   H = H - (Math.abs(timezone_offset) / 60)
        // } else {
        //   H = H + (Math.abs(timezone_offset) / 60)
        // }

        return new Date(Y, m, d, H, i).getTime();
      },
      //
      keyNameToKeyOptions(array) {
        return _.map(array, (item) => {
          return {
            id: item.id,
            option: item.name
          }
        })
      },
      //
      getAccountId() {
        return AMOCRM.constant('account').id
      },
      //
      getWidgetId(postfix, hash) {
        hash = typeof hash !== 'undefined' ? hash : true;
        postfix = typeof postfix !== 'undefined' ? postfix : '';
        return (hash ? '#' : '') + self.params.widget_code + (postfix ? '_' + postfix : '');
      },
      //
      openNotifications(text, type = 'success') {
        const params = {
          header: ai_widget_name,
          text: text
        };
        if (type === 'success') {
          AMOCRM.notifications.show_message(params);
          return true;
        }
        if (type === 'error') {
          AMOCRM.notifications.show_message_error(params);
          return true;
        }
        return false;
      },
      openAlert(text, type = 'success') {
        if (type === 'success') {
          return new Modal()._showSuccess(text, false, 3000);
        }
        if (type === 'error') {
          return new Modal()._showError(text, false);
        }
        return false;
      },
      openModal(data, class_name) {
        self.openedModal = new Modal({
          class_name: 'modal-list ' + class_name,
          init: function ($modalBody) {
            const $this = $(this);
            $modalBody
              .trigger('modal:loaded')
              .html(data)
              .trigger('modal:centrify')
              .append('<span class="modal-body__close"><span class="icon icon-modal-close"></span></span>');
          },
          destroy: function () {
            self.openedModal = false;
          }
        });
      },
      appendCss(file) {
        if ($(`link[href="${self.base_path}/css/${file}?v=${self.params.version}"]`).length) {
          return false;
        }
        $('head').append(`<link type="text/css" rel="stylesheet" href="${self.base_path}/css/${file}?v=${self.params.version}">`);
        return true;
      },
      /**
       *
       * @param {string} querySelector
       * @param {boolean} show
       * @param {boolean} refresh
       * @returns {boolean}
       */
      loader(querySelector, show = false, refresh = false) {
        if (show === true) {
          if (refresh) {
            $(querySelector).html(
              '<div class="amoai-td_lfb-overlay-loader">' +
              '<span class="spinner-icon spinner-icon-abs-center"></span>' +
              '</div>'
            );
            return true;
          }
          $(querySelector).prepend(
            '<div class="amoai-td_lfb-overlay-loader">' +
            '<span class="spinner-icon spinner-icon-abs-center"></span>' +
            '</div>'
          );
          return true;
        }
        $(querySelector).find('.amoai-td_lfb-overlay-loader').remove();
        return true;
      },
      loadTemplates(templates, callback) {
        const templateName = templates.shift()
        if (templateName === undefined) {
          callback()
          return
        }
        if (self.templates[templateName] !== undefined) {
          self.utils.loadTemplates(templates, callback)
          return
        }
        // noinspection JSUnusedGlobalSymbols
        self.render({
          href: `/templates/${templateName}.twig`,
          base_path: self.base_path,
          load(template) {
            self.templates[templateName] = template
            self.utils.loadTemplates(templates, callback)
          }
        }, {});
      },
      /**
       *
       * @param id
       * @returns {string|null}
       */
      getInputValue(id) {
        return $('input[name="CFV[' + id + ']"]').val() || null
      },
      // инициализация Vue.js на элементе
      initializeVue(parentBLockSelector, condition = true) {
        $(parentBLockSelector).append(`<div id="${self.params.widget_code}_vue_app"></div>`)
        if (condition) {
          self.utils.appendCss('app.css')
          App.default.render(self, `#${self.params.widget_code}_vue_app`, urlAPI)
        }
      },
      // скрыть/открыть target в зависимости от checkbox
      checkboxCheckAndHide($checkbox, $target) {
        $checkbox.is(':checked') ? $target.removeClass('hidden') : $target.addClass('hidden');
      },
      // получить значение кастом поля у сущности по id сущности и id поля
      getCustomFieldValue(entity, ent_id, cf_id) {
        return new Promise(function (succeed, fail) {
          $.ajax({
            url: `https://${AMOCRM.constant('account').subdomain}.amocrm.ru/api/v4/${entity}/${ent_id}`,
            method: 'GET',
            success: function (res) {
              succeed(res.custom_fields_values)
            },
            error: function (a, b, err) {
              self.utils.openAlert(err.message ? err.message : err, 'error')
            }
          })
        }).then(res => {
          if (res) {
            return _.find(res, (field) => {
              return +field.field_id === +cf_id
            }).values[0].value || null;
          } else {
            return null;
          }
        })
      },
      // форматировать строку в цену
      formatStringToPrice(number) {
        const price = Number.prototype.toFixed.call(parseFloat(number) || 0, 2);
        //заменяем точку на запятую
        let price_sep = price.replace(/(\D)/g, ",");
        //добавляем пробел как разделитель в целых
        price_sep = price_sep.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1 ");
        return price_sep;
      },
      /**
       * форматировать строку в число
       * @param {string} string
       * @param {boolean} float - возвращать тип float или integer
       * @returns {?number}
       */
      formatStringToNumber(string, float = false) {
        const s = string.replace(/\,/g, '.').replace(/ /g, '');
        if (float) {
          return parseFloat(s)
        } else {
          return parseInt(s)
        }
      },
      /**
       * Редактирование карточек по API
       * @param {string} entity - сущность
       * @param {array} data - массив данных
       */
      patchAmoCardsApiRequest(entity, data) {
        return new Promise(function (resolve, reject) {
          $.ajax({
            url: `https://${AMOCRM.constant('account').subdomain}.amocrm.ru/api/v4/${entity}`,
            type: "PATCH",
            dataType: "json",
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function (res, status) {
              resolve(res)
            },
            error: function (a, b, err) {
              console.log('patchAmoCardsApiRequest Error - ', a, b, err)
              reject(err)
            }
          })
        })
      }
    };
    /**
     *
     */
    self.widget_methods = {
      // Кол-во активных пользователей
      activeUsers() {
        let users = 0;
        for (const manager in AMOCRM.constant('managers')) {
          if (AMOCRM.constant('managers')[manager].active === true) {
            users++;
          }
        }
        return users;
      },
      // Пользователи на аккаунте
      amoUsers() {
        return _.map(AMOCRM.constant('account').users, function (val, indx) {
          return {
            id: indx,
            name: val
          }
        });
      },
      // Добавляем кнопку отправить данные компании в Юр отдел
      addButtonSendToLegal() {
        $('input[name="CFV[' + FIELD_ID.legal_confirm + ']"]').each(function (i) {
          if (!$(this).attr('checked')) {
            $(this).parents('.linked-forms__group-wrapper').append(
              `<div class="linked-form__field linked-form__field-amoai-tdlfb">` +
              `<div class="linked-form__field__label linked-form__field__label-amoai-tdlfb">` +
              `<span>Cогласование c Юр.Отделом</span>` +
              `</div>` +
              `<button type="button" class="button-input button-input_blue" id="js-amoai-tdlfb-sendToLegal">` +
              `<span class="button-input-inner "><span class="button-input-inner__text">Отправить</span></span></button>` +
              `</div>`
            )
          }
        })
      },
      // отправить предварительный запрос на 1с
      sendPreLegalRequest(inn) {
        return new Promise(function (succeed, fail) {
          $.ajax({
            url: urlAPI + '/ajax_1c/pre_legal_request',
            method: 'get',
            data: {
              account_id: self.utils.getAccountId(),
              inn
            },
            success: function (res) {
              if (res.success) {
                succeed(res.payload);
              } else {
                fail(res.error);
              }
            },
            error: function (a, b, err) {
              fail(err)
            },
          })
        })
      },
      // обработка запроса в Юр отдел
      sendLegalRequest(t) {
        const inn = $('#card_holder .card-fields__fields-block input[name="CFV[' + FIELD_ID.inn + ']"]').val();
        if (inn === '') {
          self.utils.openAlert('Поле "ИНН" должно быть заполнено', 'error');
          return;
        }
        t.trigger('button:load:start');
        // отправим запрос в базу на получение id
        this.sendPreLegalRequest(inn).then(res => {
          console.log('response', res)
          if (res.status === 'success') {
            return res.response.id;
          }
          if (res.status === 'error') {
            throw new Error(res.response.message)
          }
        }).then(id => {
          console.log('id', id)
          // обновим карточку компании
          return this.updateCardCustomField({
            field_id: FIELD_ID.id_1c,
            value: id,
            entity_id: AMOCRM.constant('card_element').id
          }, 'companies');
        }).then(res => {
          console.log('after updateCardCustomField', res)
          if (res.status) {
            // отправим данные карточки для формирования excel и отправки письма
            return this.getCompanyCardInfo(AMOCRM.constant('card_element').id).then(res => {
              console.log('handleCompanycardInfo', res)
              // проверим заполнение полей
              if(res.hasOwnProperty('custom_fields_values') && this.validateCompanyFullfilment(res.custom_fields_values)) {
                // передадим данные для обработки, и отправки excel
                return this.handleCompanyCardInfo(res);
              } else {
                const msg = '<span style="font-weight:600;font-size:18px;border-bottom:1px solid red;">Необходимо заполнить следующие поля</span>';
                throw new Error(msg + '<div style="color:#31444d;">' + self._validate_company_errors.join(', <br>') + '</div>')
              }
            })
          } else {
            throw new Error('Ошибка обновления полей компании');
          }
        }).then(res => {
          console.log('after HandleCompanyCardInfo')
          self.utils.openAlert('Извещение отправлено на почту');
          // обновим поле время отправки
          this.updateCardCustomField({
            field_id: FIELD_ID.legal_request_data_time,
            value: Math.ceil(new Date().getTime()/1000),
            entity_id: AMOCRM.constant('card_element').id
          }, 'companies');
        }).catch(err => {
          self.utils.openAlert(err.message, 'error');
        }).finally(()=>{
          t.trigger('button:load:stop');
        })
      },
      // передача данных компании для обработки и отправки письма
      handleCompanyCardInfo(data = {}) {
        return new Promise(function (succeed, fail) {
          if (data.hasOwnProperty('account_id')
            && data.hasOwnProperty('id')
            && data.hasOwnProperty('custom_fields_values')) {
            $.ajax({
              url: urlAPI + '/legal',
              method: "POST",
              data: data,
              success: function (res) {
                console.log('handleCompanyInfo', res)
                if (res.success) {
                  succeed(res);
                } else {
                  fail(res.error)
                }
              },
              error: function (a, b, err) {
                fail(err);
              }
            })
          } else {
            console.log('handleCompanyCardInfo error - "Not enough data"', data);
            fail("Not enough data");
          }

        })
      },
      // получим данные по карточке компании
      getCompanyCardInfo(card_id) {
        return new Promise(function (succeed, fail) {
          $.ajax({
            url: `https://${AMOCRM.constant('account').subdomain}.amocrm.ru/api/v4/companies/` + card_id,
            type: "GET",
            dataType: "json",
            contentType: 'application/json',
            data: {
              with: 'contacts'
            },
            success: function (data) {
              // добавим информации
              data.responsible_user_name = AMOCRM.constant('account').users[+data.responsible_user_id]
              succeed(data)
            },
            error: function (a, b, err) {
              console.log('getCompanyCardInfo Error', err, a, b)
              self.utils.openAlert('Error: ' + err)
            },
          })
        })
      },
      /**
       * Обновление custom fields заданной сущности
       * @param {object} data - {
       *   entity_id,
       *   field_id,
       *   value
       * }
       * @param {string} entity - сущность для обновления (companies, leads)
       * @returns {Promise<unknown>}
       */
      updateCardCustomField(data, entity) {
        return new Promise(function (resolve, reject) {
          $.ajax({
            url: `https://${AMOCRM.constant('account').subdomain}.amocrm.ru/api/v4/${entity}/${data.entity_id}`,
            type: "PATCH",
            dataType: "json",
            contentType: 'application/json',
            data: JSON.stringify({
              custom_fields_values: [
                {
                  field_id: data.field_id,
                  values: [
                    {
                      value: data.value
                    }
                  ]
                }
              ]
            }),
            success: function (res, status) {
              resolve({
                status: true
              })
            },
            error: function (a, b, err) {
              console.log('updateCardCustomField ajax.error', err)
              self.utils.openAlert('Error ' + err, 'error')
            }
          })
        })
      },
      // получить id менеджера в 1С
      getCManagerId(amo_id) {
        return new Promise(function (succeed, fail) {
          $.ajax({
            url: urlAPI + '/settings/get',
            method: 'GET',
            data: {
              account_id: self.utils.getAccountId()
            },
            success: function (res) {
              if (res.success) {
                const users_match = res.payload.response.users_match;
                const user = _.find(users_match, (user) => {
                  return +user.amo_user_id === +amo_id
                });
                if (user) {
                  succeed(user.c_user_id)
                } else succeed('0')
              } else {
                fail(res.error)
              }
            },
            error: function (a, b, err) {
              fail(err)
            }
          })
        })
      },
      /**
       * Отправить запрос к api - /ajax_1c
       * @param {string} point - endpoint "order"
       * @param {string} method - GET/POST ... other
       * @param {object} data
       */
      send1CApiRequest(point, method, data = {}) {
        data['account_id'] = self.utils.getAccountId();
        return new Promise(function (resolve, reject) {
          $.ajax({
            url: `${urlAPI}/ajax_1c/${point}`,
            method: method,
            data: data,
            success: function (res) {
              resolve(res)
            },
            error: function (a, b, err) {
              console.log(a, b, err)
              reject(err)
            },
          })
        })
      },
      /**
       * Получить настройки из БД
       * @param {string} setts
       */
      getSettings(setts = '') {
        return new Promise(function (resolve, reject) {
          $.ajax({
            url: urlAPI + '/settings/get',
            method: 'GET',
            data: {
              account_id: self.utils.getAccountId()
            },
            success: function (res) {
              if (res.success) {
                resolve(res.payload.response.settings)
              } else {
                reject(res.error)
              }
            },
            error: function (a, b, err) {
              reject(err)
            }
          })
        })
      },
      /**
       *
       * @param {float} price
       * @param {float} vat_rate
       * @param {boolean} nds_included
       * @returns {{vat_sum: {string}, price_with_vat: {string}}}
       */
      calculateVat(price, vat_rate, nds_included) {
        let vat_sum, price_with_vat;
        if (nds_included) {
          vat_sum = (price * vat_rate) / (100 + vat_rate);
          price_with_vat = price;
        } else {
          vat_sum = (price * vat_rate) / 100;
          price_with_vat = price + vat_sum;
        }
        return {
          vat_sum: vat_sum.toFixed(2),
          price_with_vat: price_with_vat.toFixed(2),
        }
      },
      validateCompanyFullfilment(custom_fields_values) {
        const must_be = [
          {
            id: FIELD_ID.full_name,
            name: 'Наименование полное'
          },
          {
            id: FIELD_ID.kpp,
            name: 'КПП'
          },
          {
            id: FIELD_ID.adress,
            name: 'Адрес'
          },
          {
            id: FIELD_ID.mainaddress,
            name: 'Фактический адрес'
          },
          {
            id: FIELD_ID.delivery,
            name: 'Адрес Доставки'
          },
          {
            id: FIELD_ID.phone,
            name: 'Телефон'
          },
          {
            id: FIELD_ID.email,
            name: 'Email'
          },
          {
            id: FIELD_ID.bank,
            name: 'Банк'
          },
          {
            id: FIELD_ID.activity,
            name: 'Вид деятельности'
          },
          {
            id: FIELD_ID.delivery_time_from,
            name: 'Время доставки с'
          },
          {
            id: FIELD_ID.delivery_time_to,
            name: 'Время доставки по'
          },
          {
            id: FIELD_ID.account,
            name: 'Номер счета'
          },
          {
            id: FIELD_ID.comprole,
            name: 'Юр./физ. лицо'
          },
        ]
        self._validate_company_errors = []
        $.each(must_be, function(indx, must_be){
          let pass = true;
          $.each(custom_fields_values, function(indz, cf){
            if(must_be.id == cf.field_id) {
              pass = false;
            }
          })
          if(pass){
            self._validate_company_errors.push(must_be.name)
          }
        })

        return self._validate_company_errors.length === 0;
      },
      // запрос у env id полей
      getFieldIdsFromBack() {
        return new Promise(function(resolve, reject){
          $.ajax({
            url: urlAPI + '/settings/field_ids',
            method: 'GET',
            data: {
              account_id: self.utils.getAccountId()
            },
            success: function (res) {
              resolve(res.payload.fieldIds)
            },
            error: function (a, b, c) {
              reject('Ошибка получения настроек виджета #704')
            }
          })
        })
      }
    }; // END self.widget_methods
    /**
     *
     */
    self.nomenclature_methods = {
      eventNamesCode: 'nomenclature_methods',
      /**
       * SELECT NOMENCLATURE - запуск выбора продукта из перечня номенклатуры
       * @param t
       */
      selectProductsWorker(t) {
        this.bindEventHandlers();
        if (t.attr('data-field-hash') && t.attr('data-field-type')) {
          $('.amoai-td_lfb-products_modal').remove();
          $.ajax({
            url: urlAPI + '/ajax_1c/product_groups',
            method: 'GET',
            data: {
              account_id: self.utils.getAccountId()
            },
            success: function (res) {
              console.log(res)
              if (res.success && res.payload.status === 'success') {
                let html = self.templates['products_choose'].render({
                  self: self,
                  hash: t.attr('data-field-hash'),
                  field_type: t.attr('data-field-type'),
                  prod_groups: self.nomenclature_methods.parseProductGroups(res.payload.response),
                })
                self.utils.openModal(html, 'amoai-td_lfb-products_modal');
                const group_id = t.parents('tr').find('input[name="group_id"]').val();
                if (group_id !== '') {
                  const js = $('.js-amoai-td_lfb-groups_list');
                  const js_offset = js.offset().top;
                  const a = $('.js-amoai-td_lfb-groups_list li[data-group-id="' + group_id + '"]');
                  let a_offset = a.offset().top;
                  if (a_offset === 0) {
                    a_offset = a.parents('.list-with-child').offset().top
                  }
                  a.parents('li.js-amoai-td_lfb-pick_group').trigger('click.' + self.nomenclature_methods.eventNamesCode);
                  a.trigger('click.' + self.nomenclature_methods.eventNamesCode);
                  js.animate({
                    scrollTop: (a_offset - js_offset) - 100
                  }, 100)
                }
              }
              if (res.success && res.payload.status === 'error') {
                self.utils.openAlert(res.payload.response.message, 'error')
              }
              if (res.success === false) {
                self.utils.openAlert(res.error.body, 'error')
              }
            },
            error: function (a, b, err) {
              console.log(a, b, err)
              self.utils.openAlert(err, 'error');
            }
          })
        }
      },
      // обработка иерархии номенклатурных групп
      parseProductGroups(data = []) {
        if (!data.length) {
          return [];
        }
        data.shift();
        let parents = [];
        //
        for (const group of data) {
          let isParent = false
          for (const parent of parents) {
            if (group.parent.id === parent.id) {
              isParent = true;
              break;
            }
          }
          if (!isParent) {
            parents.push(group.parent)
          }
        }
        //
        for (const parent of parents) {
          parent.child = [];
        }
        //
        for (const datum of data) {
          for (const parent of parents) {
            if (String(parent.id) === String(datum.parent.id)) {
              parent.child.push(datum);
            }
          }
        }
        // значит есть вложенные подкатегории
        if (parents.length > 1) {
          for (const parent of parents) {
            for (const parent_for_child of parents) {
              if (String(parent.id) === String(parent_for_child.id)) {
                continue;
              }
              for (const child of parent_for_child.child) {
                if (String(parent.id) === String(child.id)) {
                  child.child = [];
                  child.child = parent.child;
                  let indx = parents.indexOf(parent);
                  parents.splice(indx, 1)
                }
              }
            }
          }
        }
        return parents[0].child
      },
      // номенклатура по id группы
      chooseGroup(t) {
        if (t.attr('data-group-id')) {
          t.parent('ul').find('li').removeClass('amoai-td_lfb-group_selected');
          t.addClass('amoai-td_lfb-group_selected');
          if (t.hasClass('list-with-child')) {
            t.addClass('list-opened')
            return;
          }
          this.getProductsByParams(t.attr('data-group-id')).then(res => {
            console.log(res)
            const html = self.templates['product_row'].render({
              self: self,
              products: res.response
            })
            $('.js-amoai-td_lfb-products_container').html(html)
          }).catch(err => {
            self.openAlert(err.error.body, 'error')
          })
        }
      },
      getProductsByParams(id = '', name = '') {
        return new Promise(function (succeed, fail) {
          $.ajax({
            url: urlAPI + '/ajax_1c/products_search',
            method: 'POST',
            data: {
              account_id: self.utils.getAccountId(),
              prod_id: id || null,
              name: name || null
            },
            success: function (res) {
              console.log(res)
              if (res.success) {
                succeed(res.payload)
              } else {
                fail(res.error)
              }
            },
            error: function (a, b, err) {
              console.log(err)
              self.utils.openAlert(err, 'error')
            }
          })
        })
      },
      // поиск продукта по названию
      searchForProducts(t) {
        if (t.val().trim().length < 3) {
          return;
        }
        let group_id, name = t.val().trim();
        if ($('.js-amoai-td_lfb-prod_hierarchy_checkbox').prop('checked')) {
          group_id = $('.js-amoai-td_lfb-groups_list li.amoai-td_lfb-group_selected').last().attr('data-group-id') || '';
        }
        this.getProductsByParams(group_id, name).then(res => {
          if (res.status === 'success') {
            let html = self.templates['product_row'].render({
              self: self,
              products: res.response,
            })
            $('.js-amoai-td_lfb-products_container').html(html)
          }
          if (res.status === 'error') {
            self.utils.openAlert(res.response.message, 'error')
            $('.js-amoai-td_lfb-products_container').html()
          }
        })
      },
      // выбор продукта
      chooseProduct(t) {
        const prod_id = t.attr('data-product-id');
        const nomenclature = t.find('td').first().html();
        const article = t.find('td').last().html();
        const target = $('.amoai-td_lfb-products_choose_container input[name="HASH"]').val();
        const target_row = $('.amoai-td_lfb-lead_order_modal .amoai-td_lfb-items_table_body tr.' + target);
        self.utils.loader('.amoai-td_lfb-products_choose_container', true);
        const data = {
          prod_id: prod_id,
          agreement: $('.amoai-td_lfb-box_container input[name="soglash"]').val(),
          storage: $('.amoai-td_lfb-box_container input[name="sklad"]').val(),
        }
        self.widget_methods.send1CApiRequest('product_info', 'get', data).then(res => {
          console.log(res)
          if (res.success && res.payload.status === 'success') {
            const vat_rate = res.payload.response.nds;
            const unit = res.payload.response.unit;
            const stock = res.payload.response.stock;
            const nds_included = res.payload.response.nds_included;
            const group_id = res.payload.response.parent_id
            const price = +res.payload.response.price;
            const vat_data = self.widget_methods.calculateVat(parseFloat(price), parseFloat(vat_rate), nds_included)
            const vat_sum = vat_data.vat_sum;
            const price_with_vat = vat_data.price_with_vat;
            // вставим значения в необходимый ряд
            target_row.find('input[name="prod_id"]').val(prod_id);
            target_row.find('input[name="group_id"]').val(group_id);
            target_row.find('input[name="article"]').val(article).attr('title', article);
            target_row.find('input[name="nomenclature"]').val(nomenclature).attr('title', nomenclature);
            target_row.find('input[name="edinica"]').val(unit).attr('title', unit);
            target_row.find('input[name="free"]').val(stock);
            target_row.find('input[name="base_summnom"]').val(price.toFixed(2));
            target_row.find('input[name="vatrate"]').val(vat_rate);
            target_row.find('input[name="nds_included"]').val(nds_included);
            target_row.find('input[name="base_vatrate"]').val(vat_rate);
            target_row.find('input[name="base_vat"]').val(vat_sum);
            target_row.find('input[name="base_summvat"]').val(price_with_vat);
            // количество в последнюю очередь для запуска пересчета цен
            target_row.find('input[name="quantitynom"]').val(1).trigger('input.' + self.lcard_methods.eventNamesCode);
            $('.amoai-td_lfb-products_modal .modal-body__close').trigger('click');
          }
          if (res.success && res.payload.status === 'error') {
            console.log(res.payload.response)
            self.utils.openAlert(res.payload.response.message, 'error')
          }
        }).catch(err => {
          console.log(err)
          self.utils.openAlert(err, 'error')
        }).finally(() => {
          self.utils.loader('.amoai-td_lfb-products_choose_container');
        })
      },
      //
      bindEventHandlers(widget_code = 'nomenclature_methods') {
        $body.off('.' + widget_code)
          // выбор продукта из списка
          .on('click.' + widget_code, '.js-amoai-td_lfb-choose_product', function (e) {
            self.nomenclature_methods.chooseProduct($(this));
          })
          // поиск продукта
          .on('input.' + widget_code, '.js-amoai-td_lfb-products_search', function (e) {
            self.nomenclature_methods.searchForProducts($(this));
          })
          // выбор группы номенклатуры
          .on('click.' + widget_code, '.js-amoai-td_lfb-pick_group', function (e) {
            e.stopPropagation();
            self.nomenclature_methods.chooseGroup($(this));
          })
      }
    };
    /**
     *
     */
    self.lcard_methods = {
      eventNamesCode: 'lcard_methods',
      /**
       * FORM WORKER - открытие формы. Entrypoint
       * @param type - тип формы Заказ/Реализация
       */
      orderFormWorker(type) {
        this.bindEventHandler();
        if ($('.amoai-td_lfb-lead_order_modal').length) {
          self.utils.loader('.amoai-td_lfb-lead_order_modal', true)
        }
        const comp_id = $('#companies_list input[name="ID"]').val() || null;
        // нет компании - нет заказов
        if (!comp_id) {
          self.utils.openAlert('Необходимо добавить компанию к сделке', 'error');
          return;
        }
        $('body').addClass("page-loading");
        const order_id = self.utils.getInputValue(FIELD_ID.order_guid_lead_field);
        const realization_id = self.utils.getInputValue(FIELD_ID.realization_guid_lead_field);
        self._current_doc_id = type === 'order' ? order_id : realization_id;
        let order, realization, company, promises = [], realization_fulfillment = true;

        // есть компания есть Amo id
        self.utils.getCustomFieldValue('companies', comp_id, FIELD_ID.id_1c).then(res => {
          if (!res) {
            throw new Error('Не корректные данные по компании');
          }
          let promise_helper = [];
          // получим данные по компании из 1С
          promises.push(self.widget_methods.send1CApiRequest('comp_datas', 'get', {comp_id: res}))
          promise_helper.push('comp_data')
          // получим данные по заказу
          if (type === 'order' && order_id) {
            promises.push(self.lcard_methods.getOrderInformationFromC(type, order_id))
            promise_helper.push(type)
          }
          // получим данные по реализации
          if (type === 'realization') {
            // если заполнено поле значит открываем реализацию
            if (realization_id) {
              promises.push(self.lcard_methods.getOrderInformationFromC(type, realization_id));
              realization_fulfillment = false;
              // если поле пустое значит надо создать новую реализацию для этого передается id заказа
            } else {
              promises.push(self.lcard_methods.realizationFulfillment(order_id))
            }
            promise_helper.push(type)
          }

          return Promise.all(promises).then(result => {
            console.log('after comp_datas and getOrderInformation', result)
            let data = {
              comp_data: {},
              order: {},
              realization: {},
            }
            if (!result[0].success) {
              self.utils.openAlert(result[0].error.body + '<br>Невозможно продолжать работу!', 'error')
              throw new Error('Невозможно продолжать работу!')
            }
            if (result[0].success) {
              data.comp_data = result[0].payload
            }
            if (promise_helper.length > 1) {
              if (promise_helper[1] === 'order') {
                data.order = result[1]
              }
              if (promise_helper[1] === 'realization') {
                data.realization = result[1]
              }
            }
            return data;
          })
        }).then(res => {
          console.log(res)
          let init_option = {id: '', option: 'Выбрать'};

          const data = {
            is_new: order_id === null,
            sposdost: {
              items: res.comp_data ? self.utils.keyNameToKeyOptions(res.comp_data.sposdost) : [init_option],
              selected: res[type].presets && res[type].presets.sposdost ? res[type].presets.sposdost : ''
            },
            gruzopoluchatel: {
              items: res.comp_data ? self.utils.keyNameToKeyOptions(res.comp_data.gruzopoluchatel) : [init_option],
              selected: res[type].presets && res[type].presets.gruzopoluchatel ? res[type].presets.gruzopoluchatel : '',
            },
            soglash: {
              items: res.comp_data ? self.utils.keyNameToKeyOptions(res.comp_data.agreements) : [init_option],
              selected: res[type].presets && res[type].presets.agreements ? res[type].presets.agreements : '',
            },
            dogovor: {
              items: res.comp_data ? self.utils.keyNameToKeyOptions(res.comp_data.dogovor) : [init_option],
              selected: res[type].presets && res[type].presets.dogovor ? res[type].presets.dogovor : '',
            },
            sklad: {
              items: res.comp_data ? self.utils.keyNameToKeyOptions(res.comp_data.sklad) : [init_option],
              selected: res[type].presets && res[type].presets.sklad ? res[type].presets.sklad : '',
            },
            yurcomp: {
              items: res.comp_data ? self.utils.keyNameToKeyOptions(res.comp_data.yurcomp) : [init_option],
              selected: res[type].presets && res[type].presets.yurcomp ? res[type].presets.yurcomp : '',
            },
            kontragent: {
              id: $(`textarea[name="CFV[${FIELD_ID.id_1c}]"]`).val(),
              option: $(`textarea[name="CFV[${FIELD_ID.full_name}]"]`).first().val()
            },
            items: res[type].items ? res[type].items : []
          }
          if (res[type] && res[type].presets) {
            let datadost = res[type].presets.datadost ? res[type].presets.datadost : '';
            if (type === 'realization') {
              datadost = res[type].presets.date ? res[type].presets.date : '';
              if(realization_fulfillment) {
                self.lcard_methods.setInterval(type)
              }
            }
            let vremdost_from = res[type].presets.vremdost_from ? res[type].presets.vremdost_from : ''
            let vremdost_to = res[type].presets.vremdost_to ? res[type].presets.vremdost_to : ''
            if (datadost !== '') {
              const d = datadost.split('T')[0]
              datadost = new Date(d).toLocaleDateString();
            }
            if (vremdost_from !== '') {
              vremdost_from = "01.01.2001 " + vremdost_from.split('T')[1].slice(0, 5);
            }
            if (vremdost_to !== '') {
              vremdost_to = "01.01.2001 " + vremdost_to.split('T')[1].slice(0, 5);
            }
            data['datadost'] = datadost
            data['vremdost_to'] = vremdost_to
            data['vremdost_from'] = vremdost_from
            data['order_number'] = res[type].presets.numberbill ? res[type].presets.numberbill : '...'
            data['order_date'] = res[type].presets.date ? new Date(res[type].presets.date).toLocaleString() : '...'
            data['id'] = res[type].presets.id ? res[type].presets.id : ''
            data['skidka'] = res[type].presets.skidka ? res[type].presets.skidka : 0
            data['amount'] = res[type].presets.amount ? res[type].presets.amount : 0
            data['total_vat'] = res[type].presets.vat_amount ? res[type].presets.vat_amount : 0

            // обновим поля сделки
            const lead_order_field = {
              'order': FIELD_ID.order_number_lead_field,
              'realization': FIELD_ID.realization_number_lead_field,
            }
            const amodata = [{
              id: AMOCRM.data.current_card.id,
              custom_fields_values: [{
                field_id: FIELD_ID.payment_budget,
                  values: [
                    {
                      value: String(data['amount'].toFixed(2))
                    }
                  ]
              },{
                field_id: lead_order_field[type],
                values: [
                  {
                    value: data['order_number'] + ' от ' +  data['order_date']
                  }
                ]
              },]
            }];
            self.utils.patchAmoCardsApiRequest('leads', amodata);

          }
          // сохраним данные грузополучателей в памяти для подстановки времени
          self._delivery_points = {};
          if (res.comp_data && res.comp_data.gruzopoluchatel) {
            for (const gruzopoluchatel of res.comp_data.gruzopoluchatel) {
              self._delivery_points[gruzopoluchatel.id] = gruzopoluchatel
            }
          }

          if(self.lcard_methods.checkForRealizationButton()) {
            data['isSaved'] = true;
          }

          this.renderOrderForm(type, data);
          if (data['is_new']) {
            $('.js-amoai-td_lfb-gruzopoluchatel_select input').trigger('controls:change.' + self.lcard_methods.eventNamesCode);
          }
        }).catch(err => {
          console.log('OrderFormWorker Catch error', err)
          self.utils.openAlert(err.message ? err.message : err, 'error')
        }).finally(() => {
          $('#makeRealization').trigger('button:load:stop')
          $('body').removeClass("page-loading");
          self.utils.loader('.amoai-td_lfb-lead_order_modal')
        })
      },
      /**
       * SEND ORDER - отправка формы в 1С
       * @param {string} type - order или realization
       */
      saveAndSendOrderWorker(type) {
        self.utils.loader('.amoai-td_lfb-lead_order_modal_wrapper', true)
        let new_order = true;
        const order_datas = {};
        order_datas.presets = {
          type
        };
        const id_in_field = self.utils.getInputValue(FIELD_ID[type + '_guid_lead_field']);
        if (id_in_field) {
          order_datas.presets['id'] = id_in_field;
          new_order = false;
        }
        order_datas.items = [];
        // подготовим
        const presets = new Promise(function (resolve, reject) {
          $('.amoai-td_lfb-lead_order_modal .amoai-td_lfb-box_container input').each(function (indx) {
            order_datas.presets[$(this).attr('name')] = $(this).val();
            if ('vremdost_from' === $(this).attr('name') || 'vremdost_to' === $(this).attr('name')) {
              const val = $(this).val()
              let date = '0001-01-01';
              const time = val.split(' ')[1];
              order_datas.presets[$(this).attr('name')] = date + 'T' + time + ':00';
            }
            if ('datadost' === $(this).attr('name')) {
              const val = self.utils.amoDateStringToTimestamp($(this).val());
              order_datas.presets[$(this).attr('name')] = new Date(val).toISOString().split('T')[0]
            }
          })
          resolve('presets');
        })
        // подготовим
        const items = new Promise(function (succeed, fail) {
          $('.js-amoai-td_lfb-saved_items tr').each(function (i) {
            let row_inp = {};
            $(this).find('input').each(function (z) {
              row_inp[$(this).attr('name')] = $(this).val();
            })
            order_datas.items.push(row_inp);
          })
          succeed('items');
        })
        Promise.all([
          self.widget_methods.getCManagerId(AMOCRM.data.current_card.main_user),
          presets,
          items
        ]).then(res => {
          order_datas.presets['manager'] = res[0];
          order_datas['account_id'] = self.utils.getAccountId();
          console.log('Order datas - ', order_datas);
          return self.lcard_methods.sendOrderData(order_datas, type)
        }).then(res => {
          console.log('after SendOrderData', res)
          const doc_number = self._doc_number = res.response.doc_number;
          const id = self._id = res.response.id;

          // обновим поля сделки
          // const price = self.utils.formatStringToNumber($('.js-amoai-td_lfb-total_summvat').text());
          return self.widget_methods.getSettings().then(res => {
            console.log('getsettings res', res)
            const status_check = res[0].status_check;
            const statuses = res[0].statuses;
            const data = [{
              id: AMOCRM.data.current_card.id,
              // price: price,
              custom_fields_values: [
                {
                  field_id: FIELD_ID.order_guid_lead_field,
                  values: [
                    {
                      value: id
                    }
                  ]
                },
                {
                  field_id: FIELD_ID.order_number_lead_field,
                  values: [
                    {
                      value: doc_number
                    }
                  ]
                },
                {
                  field_id: FIELD_ID.realization_number_lead_field,
                  values: [
                    {
                      value: ''
                    }
                  ]
                },
                {
                  field_id: FIELD_ID.realization_guid_lead_field,
                  values: [
                    {
                      value: ''
                    }
                  ]
                }
              ]
            }]
            if (status_check === 1) {
              const s = statuses.split('_');
              data[0]['pipeline_id'] = +s[0]
              data[0]['status_id'] = +s[1]
            }
            return self.utils.patchAmoCardsApiRequest('leads', data)
          })

        }).then(res => {
          console.log('after patchAmoCardsApiRequest', res)
          self.lcard_methods.setInterval(type);
          return self.lcard_methods.orderFormWorker('order')
        }).then(res=>{
          self.utils.openAlert((type === 'order' ? 'Заказ ' : 'Реализация ') + "успешно " + (new_order ? 'создан' : 'изменён') + ".  Номер - " + self._doc_number + "  ID - " + self._id)
          // $('#makeRealization').removeClass('hidden');
        }).catch(err => {
          console.log('catch error SaveAndSendOrder', err)
          self.utils.openAlert(err.message, 'error')
        }).finally(() => {
          self.utils.loader('.amoai-td_lfb-lead_order_modal_wrapper', false)
        })
      },
      /**
       * Api endpoint
       * @param {object} data - данные
       * @param {string} type - заказ или реализация
       * @returns {Promise<unknown>}
       */
      sendOrderData(data = {}, type) {
        return new Promise(function (resolver, reject) {
          $.ajax({
            url: urlAPI + '/ajax_1c/' + type,
            method: 'POST',
            data: data,
            success: function (res) {
              console.log(res)
              if (res.success && res.payload.status === 'success') {
                resolver(res.payload)
              } else if (res.success && res.payload.status === 'error') {
                reject(res.payload.response)
              } else {
                self.utils.openAlert(res.error.body)
              }
            },
            error: function (a, b, err) {
              console.log(err)
              reject(err)
            }
          })
        })
      },
      // проставить время доставки
      setDeliveryTime(t) {
        const delivery_point = t.val()
        const delivery_point_data = self._delivery_points[delivery_point];
        if (delivery_point && delivery_point_data) {
          const from = "01.01.2001 " + delivery_point_data.delivery_time_from.split('T')[1].slice(0, 5);
          const to = "01.01.2001 " + delivery_point_data.delivery_time_to.split('T')[1].slice(0, 5);
          $('.amoai-td_lfb-delivery_box input[name="vremdost_from"]').val(from)
          $('.amoai-td_lfb-delivery_box input[name="vremdost_to"]').val(to)
        }
      },
      // интервал обновления вкладки
      setInterval(type) {
        const timer = setInterval(()=>{
          let check = self.utils.getInputValue(FIELD_ID.order_guid_lead_field);
          if(type === 'realization') {
            check = self.utils.getInputValue(FIELD_ID.realization_guid_lead_field)
          }
          if(check) {
            self.lcard_methods.addLinkToOrders();
            self.lcard_methods.newOrderButton();
            clearInterval(timer)
          }
        }, 1000)
      },
      // добавить класс
      addLinkToOrders() {
        const $order_field = $('.linked-form__field[data-id="' + FIELD_ID.order_guid_lead_field + '"]');
        const $realization_field = $('.linked-form__field[data-id="' + FIELD_ID.realization_guid_lead_field + '"]');
        const $order_number_field = $('.linked-form__field[data-id="' + FIELD_ID.order_number_lead_field + '"]')
        const $realization_number_field = $('.linked-form__field[data-id="' + FIELD_ID.realization_number_lead_field + '"]')

        const $order_id = self.utils.getInputValue(FIELD_ID.order_guid_lead_field);
        const $realization_id = self.utils.getInputValue(FIELD_ID.realization_guid_lead_field);

        $order_field.addClass('hidden');
        $realization_field.addClass('hidden');

        if ($order_id) {
          $order_number_field.addClass('js-amoai-td_lfb-link_to_order_modal amoai-td_lfb-link_to_order')
        } else {
          $order_number_field.removeClass('js-amoai-td_lfb-link_to_order_modal amoai-td_lfb-link_to_order')
        }

        if ($realization_id) {
          $realization_number_field.addClass('js-amoai-td_lfb-link_to_order_modal amoai-td_lfb-link_to_order')
        } else {
          $realization_number_field.removeClass('js-amoai-td_lfb-link_to_order_modal amoai-td_lfb-link_to_order')
        }
      },
      /**
       *
       * @param {string} type - order/realization
       * @param {string|int} id
       * @returns {Promise<{object}>}
       * @throws error - res.payload.data.message
       */
      getOrderInformationFromC(type, id) {
        return self.widget_methods.send1CApiRequest(type, 'get', {id: id}).then(res => {
          if (res.success && res.payload.status === 'success') {
            return res.payload.response
          } else if (res.success && res.payload.status === 'error') {
            throw new Error(res.payload.response.message)
          }
        })
      },
      /**
       * Отправить ордер айди для заполнения реализации
       * @param {int} order_id
       * @returns {Promise<*>}
       */
      realizationFulfillment(order_id) {
        const data = {
          id: order_id,
          lead_id: AMOCRM.data.current_card.id
        }
        return self.widget_methods.send1CApiRequest('realization', 'post', data).then(res => {
          if (res.success && res.payload.status === 'success') {
            return res.payload.response
          } else if (res.success && res.payload.status === 'error') {
            throw new Error(res.payload.response.message)
          }
        })
      },
      // рендер формы
      renderOrderForm(type, data) {
        $('.amoai-td_lfb-lead_order_modal').remove();
        self.utils.loadTemplates(['lead_order', 'lead_realization', 'products_choose', 'product_row'], async function () {
          const html = self.templates['lead_order'].render({
            self: self,
            template: type,
            fields: data,
            items: data.items
          });
          self.utils.openModal(html, 'amoai-td_lfb-lead_order_modal')
          self.lcard_methods.recountRow($('.amoai-td_lfb-items_table_body'))
        })
      },
      // пересчет нумерации ряда в таблице
      recountRow(t) {
        t.find('tr').each(function (indx) {
          let hash = 'row_' + (indx + 1);
          $(this).removeClass().addClass(hash);
          $(this).find('td.js-amoai-td_lfb-row_count').html(indx + 1)
          $(this).find('input[name="article"]').attr('data-field-hash', hash)
          $(this).find('input[name="nomenclature"]').attr('data-field-hash', hash)

        })
      },
      // добавить кнопку новый заказ/реализация
      newOrderButton() {
        $('.linked-forms__group-wrapper[data-id="leads_17971623671912"]').each(function (i) {
          $('.js-amoai-td_lfb-new_button_field').remove();
          let new_button = false;
          if (!new_button && $(this).find('input[name="CFV[' + FIELD_ID.order_guid_lead_field + ']"]').val() === '') {
            new_button = {
              text: 'заказ',
              action: 'order'
            };
          }
          if (!new_button && $(this).find('input[name="CFV[' + FIELD_ID.realization_guid_lead_field + ']"]').val() === '') {
            new_button = {
              text: 'реализацию',
              action: 'realization'
            };
          }
          if (new_button) {
            $(this).append(
              `<div class="linked-form__field js-amoai-td_lfb-new_button_field">` +
              `<button type="button" class="button-input js-amoai-td_lfb-new_button" data-action="` + new_button.action + `">Создать ` + new_button.text + `</button>` +
              `</div>`)
          }
        })
      },
      // пересчет цен в ряду и общую сумму
      recalculateOrderPrices(t) {
        const target_row = t;
        const quantity = +target_row.find('input[name="quantitynom"]').val();
        const price = +target_row.find('input[name="base_summnom"]').val();
        const vat_rate = +target_row.find('input[name="vatrate"]').val();
        const nds_included = target_row.find('input[name="nds_included"]').val() === 'true';
        const vat_data = self.widget_methods.calculateVat(parseFloat(price), parseFloat(vat_rate), nds_included);

        const new_price = price * quantity;
        const new_vat = +vat_data.vat_sum * quantity;
        const new_sumvat = +vat_data.price_with_vat * quantity;
        target_row.find('input[name="summnom"]').val(new_price.toFixed(2));
        target_row.find('input[name="vat"]').val(new_vat.toFixed(2));
        target_row.find('input[name="summvat"]').val(new_sumvat.toFixed(2));

        this.recalculateTotals();
      },
      // пересчет общих сумм
      recalculateTotals() {
        let total_vat = 0, total_sumvat = 0;
        $('.js-amoai-td_lfb-saved_items tr').each(function () {
          // если отменено по причине - пропускаем
          if ($(this).find('input[name="reason"]').length) {
            return true
          }
          total_vat += +$(this).find('input[name="vat"]').val();
          total_sumvat += +$(this).find('input[name="summvat"]').val();
        })
        $('.js-amoai-td_lfb-total_vat').html(self.utils.formatStringToPrice(total_vat));
        $('.js-amoai-td_lfb-total_summvat').html(self.utils.formatStringToPrice(total_sumvat));

      },
      // обновить информацию по продуктам при изменении склада или соглашения
      updateProductsInfoOnChange(t) {
        const items = {};
        const promises = [];
        $('.js-amoai-td_lfb-saved_items tr').each(function (i) {
          self.utils.loader('.amoai-td_lfb-lead_order_modal', true);
          const row = {};
          let id = '';
          const agreement = $('.amoai-td_lfb-box_container input[name="soglash"]').val();
          const storage = $('.amoai-td_lfb-box_container input[name="sklad"]').val();
          $(this).find('input').each(function (z) {
            row[$(this).attr('name')] = $(this).val();
            if ($(this).attr('name') === 'prod_id') {
              id = $(this).val();
            }
          })
          if (id === '') {
            return true;
          }
          items[id] = row;
          promises.push(self.widget_methods.send1CApiRequest('product_info', 'get', {
            prod_id: id,
            agreement,
            storage,
          }))
        })

        Promise.all(promises).then(res => {
          console.log('updateProductsInfoOnChange', res)
          $('.js-amoai-td_lfb-saved_items tr').each(function (i) {
            if (res[i].success && res[i].payload.status === 'success') {
              const target_row = $(this);
              const vat_rate = res[i].payload.response.nds;
              const stock = res[i].payload.response.stock;
              const nds_included = res[i].payload.response.nds_included;
              const price = +res[i].payload.response.price;
              const vat_data = self.widget_methods.calculateVat(parseFloat(price), parseFloat(vat_rate), nds_included)
              const vat_sum = vat_data.vat_sum;
              const price_with_vat = vat_data.price_with_vat;

              target_row.find('input[name="free"]').val(stock);
              target_row.find('input[name="base_summnom"]').val(price.toFixed(2));
              target_row.find('input[name="vatrate"]').val(vat_rate);
              target_row.find('input[name="nds_included"]').val(nds_included);
              target_row.find('input[name="base_vatrate"]').val(vat_rate);
              target_row.find('input[name="base_vat"]').val(vat_sum);
              target_row.find('input[name="base_summvat"]').val(price_with_vat);

              self.lcard_methods.recalculateOrderPrices(target_row)
            }
            if (res[i].success && res[i].payload.status === 'error') {
              console.log(res[i].payload.response)
              self.utils.openAlert(res.payload.response.message, 'error')
              return false;
            }
          })
        }).catch(err => {
          console.log(err)
          self.utils.openAlert(err.message ? err.message : err, 'error')
        }).finally(() => {
          self.utils.loader('.amoai-td_lfb-lead_order_modal');
        })

      },
      // запрет выставлять дату доставки меньше чем текущая дата
      checkDateFields(t) {
        if (t.val() === '') {
          return;
        }
        const val = self.utils.amoDateStringToTimestamp(t.val() + " 00:00", true)
        const val_data = new Date().toLocaleDateString().split('.');
        const today = new Date(+val_data[2], +val_data[1] - 1, +val_data[0]).getTime();
        if (val < today) {
          t.val(new Date(today).toLocaleDateString())
        }
      },
      // контроль за показом кнопки реализация
      checkForRealizationButton() {
        const order_id = self.utils.getInputValue(FIELD_ID.order_guid_lead_field);
        const realization_id = self.utils.getInputValue(FIELD_ID.realization_guid_lead_field);
        // нет заказа - не показываем
        if(!order_id) {
          return false;
        }
        // есть заказ но нет реализации - показываем
        if(!realization_id) {
          return true;
        }
        // есть заказ и есть реализация - не показываем
        return false;
      },
      //
      bindEventHandler(widget_code = this.eventNamesCode) {
        $body.off('.' + widget_code)
          //
          .on('.change_control', '.amoai-td_lfb-lead_order_modal-main_section', function (e) {
            // self.lcard_methods.showRealizationButton();
          })
          // контроль изменения даты
          .on('controls:change.' + widget_code + '.change_control', 'input[name="datadost"]', function (e) {
            self.lcard_methods.checkDateFields($(this))
          })
          // смена склада и соглашения - пересмотр остатков и цен
          .on('controls:change.' + widget_code + '.change_control', '.amoai-td_lfb-deal_box input[name="sklad"], .amoai-td_lfb-deal_box input[name="soglash"]', function (e) {
            self.lcard_methods.updateProductsInfoOnChange($(this));
          })
          // открыть форму заказа из формы реализации
          .on('click.' + widget_code, '#showOrderForm', function (e) {
            if ($(this).hasClass('js-amoai-td_lfb-cancel_action')) {
              e.preventDefault()
              return;
            }
            $(this).trigger('button:load:start')
            $(this).addClass('js-amoai-td_lfb-cancel_action');
            self.lcard_methods.orderFormWorker('order')
          })
          // создать реализацию из формы заказа
          .on('click.' + widget_code, '#makeRealization', function (e) {
            if ($(this).hasClass('js-amoai-td_lfb-cancel_action')) {
              e.preventDefault()
              return;
            }
            $(this).trigger('button:load:start')
            $(this).addClass('js-amoai-td_lfb-cancel_action');
            self.lcard_methods.orderFormWorker('realization')
          })
          // добавить ряд в таблицу
          .on('click.' + widget_code + '.change_control', '#addItem', function (e) {
            self.utils.loadTemplates(['initial_items_row'], async function () {
              const html = self.templates['initial_items_row'].render({
                self: self,
                template: 'order',
              })
              $('.amoai-td_lfb-items_table tbody').append(html);
              self.lcard_methods.recountRow($('.amoai-td_lfb-items_table_body'));
            })
          })
          // удалить ряд из таблицы
          .on('click.' + widget_code + '.change_control', '.js-amoai-td_lfb-items_remove', function (e) {
            $(this).parents('tr').remove();
            self.lcard_methods.recountRow($('.amoai-td_lfb-items_table_body'));
          })
          // смена грузополучателя - смена времени доставки
          .on('controls:change.' + widget_code + '.change_control', '.js-amoai-td_lfb-gruzopoluchatel_select input', function (e) {
            self.lcard_methods.setDeliveryTime($(this));
          })
          // пересчет сумм в ряду и общих сумм
          .on('input.' + widget_code + '.change_control', '.js-amoai-td_lfb-input_quantitynom_change', function (e) {
            const target_row = $(this).parents('tr');
            self.lcard_methods.recalculateOrderPrices(target_row);
          })
          // Кнопка Сохранить и отправить заказ
          .on('click.' + widget_code, '#saveAndSendOrder', function (e) {
            self.lcard_methods.saveAndSendOrderWorker('order');
          })
          // клик по инпуту Артикул/Номенклатура
          .on('click.' + widget_code, '.js-amoai-td_lfb-article_search', function (e) {
            self.nomenclature_methods.selectProductsWorker($(this))
          })
          .on('input.change_control', '.js-amoai-td_lfb-article_search', function (e){
            return true;
          })
          // отмена/закрыть форму заказа
          .on('click.' + widget_code, '#cancelOrder', function (e) {
            $(this).parents('.modal-body').find('span.modal-body__close').trigger('click');
          })
          // печать документов
          .on('click.' + widget_code, '#saveDocument', function (e) {
            self.print_forms.PrintFormsWorker($(this));
          })
      }
    };
    /**
     *
     */
    self.print_forms = {
      eventNamesCode: 'print_forms',
      PrintFormsWorker(t) {
        t.trigger('button:load:start');
        this.bindEventHandler();
        const type = t.attr('data-type');
        const doc_type = {
          order: 'ЗаказКлиента',
          realization: 'РеализацияТоваровУслуг'
        }
        self.widget_methods.send1CApiRequest('forms/all', 'get').then(res => {
          console.log('after List print Forms', res);
          if (res.success && res.payload.status === 'success') {
            self.utils.loadTemplates(['print_forms_modal'], function () {
              const html = self.templates['print_forms_modal'].render({
                self: self,
                forms: _.filter(res.payload.response, (form) => form.doc_type === doc_type[type]),
                formats: FILE_FORMATS,
                url: `${Constants.schemaForC}://${Constants.logForC}:${Constants.passForC}@${Constants.urlForC}/print`,
                id: self._current_doc_id,
              })

              self.utils.openModal(html, 'amoai-td_lfb-print_forms_modal');
            })
          } else if (res.success && res.payload.status === 'error') {
            throw new Error(res.payload.response.message)
          }
        }).catch(err => {
          self.utils.openAlert(err, 'error')
        }).finally(() => {
          t.trigger('button:load:stop');
        })
      },
      //
      bindEventHandler(widget_code = this.eventNamesCode) {
        $body.off('.' + widget_code)
      }
    };

    // биндим главные обработчики событий
    this.bindWidgetEventHandler = function (widget_code) {
      $body.off('.' + widget_code)
        // отправить запрос на проверку в Юр. отдел
        .on('click.' + widget_code, '#js-amoai-tdlfb-sendToLegal', function (e) {
          e.preventDefault();

          self.widget_methods.sendLegalRequest($(this));
        })
        // создание новой Заказа/Реализации
        .on('click.' + widget_code, '.js-amoai-td_lfb-new_button', function (e) {
          const type = $(this).attr('data-action');
          self.lcard_methods.orderFormWorker(type);
        })
        // открыть форму заказа, существующего
        .on('click.' + widget_code, '.js-amoai-td_lfb-link_to_order_modal', function (e) {
          const type = +$(this).attr('data-id') === FIELD_ID.order_number_lead_field ? 'order' : 'realization';
          self.lcard_methods.orderFormWorker(type);
        })
    };

    this.callbacks = {
      render() {
        self.utils.appendCss('style.css');
        return true
      },
      init() {
        self.widget_methods.getFieldIdsFromBack().then(res=>{
          FIELD_ID = res
          const sys_area = self.system().area;
          if (sys_area === 'lcard') {
            self.lcard_methods.newOrderButton();
            self.lcard_methods.addLinkToOrders();
          }
          if (sys_area === 'comcard') {
            self.widget_methods.addButtonSendToLegal();
          }
          if (sys_area === 'lcard' || sys_area === 'comcard') {
            self.bindWidgetEventHandler(ai_widget_code)
          }
        }).catch(err=>{
          self.utils.openAlert(err, 'error')
        })
        return true
      },
      bind_actions() {
        // View "Settings" tab
        $body.off('change', self.utils.getWidgetId() + ' .view-integration-modal__tabs input[name="type_integration_modal"]');
        $body.on('change', self.utils.getWidgetId() + ' .view-integration-modal__tabs input[name="type_integration_modal"]', function () {
          let id = $(self.utils.getWidgetId() + ' .view-integration-modal__tabs input[name="type_integration_modal"]:checked').attr('id');
          if (id === 'setting') {
            $(self.utils.getWidgetId() + ' .view-integration-modal__keys').addClass('hidden');
            $(self.utils.getWidgetId() + ' .view-integration-modal__access').addClass('hidden');
            $(self.utils.getWidgetId() + ' .widget-settings__desc-space').addClass('hidden');
            $(self.utils.getWidgetId() + ' .view-integration-modal__setting').removeClass('hidden');
          } else {
            $(self.utils.getWidgetId() + ' .view-integration-modal__setting').addClass('hidden');
          }
        });
        // Open "OrderForm"
        $body.off('click', `#${ai_widget_code}_buy_widget_btn`);
        $body.on('click', `#${ai_widget_code}_buy_widget_btn`, function (e) {
          e.preventDefault();
          self.openOrderForm();
        });
        // Send "OrderForm"
        $body.off('click', `#${ai_widget_code}_buy_widget_send`);
        $body.on('click', `#${ai_widget_code}_buy_widget_send`, function (e) {
          e.preventDefault();
          self.sendOrderForm($(this));
        });
        return true;
      },
      settings() {
        const $modal = $('.widget-settings__modal.' + self.params.widget_code);
        const $save = $modal.find('button.js-widget-save');
        $modal.attr('id', self.utils.getWidgetId('', false)).addClass('amoai-settings');

        self.utils.loadTemplates(['modal_footer'], async function () {
          // Add footer
          const footerHtml = self.templates['modal_footer'].render({
            self: self
          });
          $modal.find('.widget-settings__wrap-desc-space').append(footerHtml);
        })

        if (self.get_install_status() !== 'installed') {
          return true;
        }
        if (self.hasSettingsTab) {
          try {
            self.utils.loadTemplates(self.settingsTemplatesArray, async function () {
              // Deactive btn & hide warning
              $modal.find('.widget_settings_block__fields').hide();

              const tabParams = {
                id: 'setting',
                text: 'Настройки'
              };
              const tabHtml = self.templates[self.tabItemTemplate].render({
                tab: tabParams
              });
              $modal.find('.view-integration-modal__tabs .tabs').append(tabHtml);
              const $modalBody = $modal.find('.modal-body');
              $modalBody.width($modalBody.width() + $('#setting').width() + 20);
              $modalBody.trigger('modal:centrify');
              const tabContentHtml = self.templates[self.tabContentTemplate].render({
                tab: tabParams
              });
              $modal.find('.widget-settings__desc-space').before(tabContentHtml);
              self.utils.initializeVue('.view-integration-modal__' + tabParams.id, App);
            })
          } catch (e) {
            self.utils.openAlert(error_msg, 'error');
          }
        }
      },
      onSave(fields) {
        return true
      },
      destroy() {

      },
    }
    return this
  }
})
