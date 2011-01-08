<?php defined("SYSPATH") or die("No direct script access.") ?>
<link rel="stylesheet" type="text/css" href="<?= url::file("modules/organize/vendor/ext/css/ext-all.css") ?>" />
<link rel="stylesheet" type="text/css" href="<?= url::file("modules/organize/vendor/ext/css/ux-all.css") ?>" />
<link rel="stylesheet" type="text/css" href="<?= url::file("modules/organize/css/organize_frame.css") ?>" />
<style type="text/css">
  .g-organize div.thumb-album div.icon {
    background-image: url(<?= url::file("modules/organize/vendor/ext/images/default/tree/folder.gif") ?>);
  }
</style>
<script type="text/javascript" src="<?= url::file("modules/organize/vendor/ext/js/ext-organize-bundle.js") ?>"></script>
<script type="text/javascript">
  Ext.BLANK_IMAGE_URL = "<?= url::file("modules/organize/vendor/ext/images/default/s.gif") ?>";
  Ext.Ajax.timeout = 1000000;  // something really large

  Ext.onReady(function() {
    /*
     * ********************************************************************************
     * Utility functions for loading data and making changes
     * ********************************************************************************
     */
    var start_busy = function(msg) {
      thumb_data_view.el.mask(msg, "loading");
    }

    var stop_busy = function() {
      thumb_data_view.el.unmask();
    }

    // Notify the parent dialog that the ExtJS app is loaded
    if (parent.done_loading) {
      parent.done_loading();
    }

    var show_generic_error = function() {
      stop_busy();
      Ext.Msg.alert(
        <?= t("An error occurred.  Consult your system administrator.")->for_js() ?>);
    }

    var current_album_id = null;
    var load_album_data = function(id) {
      if (current_album_id) {
        // Don't show the loading message on the initial load, it
        // feels a little jarring.
        start_busy(<?= t("Loading...")->for_js() ?>);
      }
      Ext.Ajax.request({
        url: '<?= url::site("organize/album_info/__ID__") ?>'.replace("__ID__", id),
        success: function(xhr, opts) {
          stop_busy();
          var album_info = Ext.util.JSON.decode(xhr.responseText);
          var store = new Ext.data.JsonStore({
            autoDestroy: true,
            fields: ["id", "thumb_url", "width", "height", "type", "title"],
            idProperty: "id",
            root: "children",
            data: album_info
          });
          current_album_id = id;
          thumb_data_view.bindStore(store);
          sort_column_combobox.setValue(album_info.sort_column);
          sort_order_combobox.setValue(album_info.sort_order);
          if (album_info.editable) {
            thumb_data_view.dragZone.unlock();
          } else {
            thumb_data_view.dragZone.lock();
          }
        },
        failure: show_generic_error
      });
    };

    var reload_album_data = function() {
      if (current_album_id) {
        load_album_data(current_album_id);
      }
    };

    var set_album_sort = function(params) {
      start_busy(<?= t("Changing sort...")->for_js() ?>);
      params["csrf"] = '<?= access::csrf_token() ?>';
      Ext.Ajax.request({
        url: '<?= url::site("organize/set_sort/__ID__") ?>'.replace("__ID__", current_album_id),
        method: "post",
        success: function() {
          stop_busy();
          reload_album_data();
        },
        failure: show_generic_error,
        params: params
      });
    }

    /*
     * ********************************************************************************
     * JsonStore, DataView and Panel for viewing albums
     * ********************************************************************************
     */
    var thumb_data_view = new Ext.DataView({
      autoScroll: true,
      enableDragDrop: true,
      itemSelector: "div.thumb",
      plugins: [
        new Ext.DataView.DragSelector({dragSafe: true})
      ],
      listeners: {
        "dblclick": function(v, index, node, e) {
          node = Ext.get(node);
          if (node.hasClass("thumb-album")) {
            var id = node.getAttribute("rel");
            tree_panel.fireEvent("click", tree_panel.getNodeById(id))
          }
        },
        "render": function(v) {
          v.dragZone = new Ext.dd.DragZone(v.getEl(), {
            ddGroup: "organizeDD",
            containerScroll: true,
            getDragData: function(e) {
              var target = e.getTarget(v.itemSelector, 10);
              if (target) {
                if (!v.isSelected(target)) {
                  v.onClick(e);
                }
                var selected_nodes = v.getSelectedNodes();
                var drag_data = {
                  nodes: selected_nodes,
                  repair_xy: Ext.fly(target).getXY()
                };
                if (selected_nodes.length == 1) {
                  drag_data.ddel = target;
                } else {
                  var drag_ghost = document.createElement("div");
                  drag_ghost.className = "drag-ghost";
                  for (var i = 0; i != selected_nodes.length; i++) {
                    var inner = document.createElement("div");
                    drag_ghost.appendChild(inner);

                    var img = Ext.get(selected_nodes[i]).dom.firstChild;
                    var child = inner.appendChild(img.cloneNode(true));
                    Ext.get(child).setWidth(Ext.fly(img).getWidth() / 2);
                    Ext.get(child).setHeight(Ext.fly(img).getHeight() / 2);
                  }
                  // The contents of the ghost float, and the ghost is wide enough for
                  // 4 images across so make sure that the ghost is tall enough.  Thumbnails
                  // are max 120px high max, and ghost thumbs are half of that, but leave some
                  // padding because IE is unpredictable.
                  drag_ghost.style.height = Math.ceil(i/4) * 72 + "px";
                  drag_data.ddel = drag_ghost;
                }
                return drag_data;
              }
            },
            getRepairXY: function() {
              return this.dragData.repair_xy;
            }
          });

          v.dropZone = new Ext.dd.DropZone(v.getEl(), {
            ddGroup: "organizeDD",
            getTargetFromEvent: function(e) {
              return e.getTarget("div.thumb", 10);
            },
            onNodeOut: function(target, dd, e, data) {
              Ext.fly(target).removeClass("active-left");
              Ext.fly(target).removeClass("active-right");
            },
            onNodeOver: function(target, dd, e, data) {
              var target_x = Ext.lib.Dom.getX(target);
              var target_center = target_x + (target.offsetWidth / 2);
              if (Ext.lib.Event.getPageX(e) < target_center) {
                Ext.fly(target).addClass("active-left");
                Ext.fly(target).removeClass("active-right");
                this.drop_side = "before";
              } else {
                Ext.fly(target).removeClass("active-left");
                Ext.fly(target).addClass("active-right");
                this.drop_side = "after";
              }
              return Ext.dd.DropZone.prototype.dropAllowed;
            },
            onNodeDrop: function(target, dd, e, data) {
              var nodes = data.nodes;
              source_ids = [];
              for (var i = 0; i != nodes.length; i++) {
                source_ids.push(Ext.fly(nodes[i]).getAttribute("rel"));
              }
              start_busy(<?= t("Rearranging...")->for_js() ?>);
              target = Ext.fly(target);
              Ext.Ajax.request({
                url: '<?= url::site("organize/rearrange") ?>',
                method: "post",
                success: function() {
                  stop_busy();
                  reload_album_data();
                },
                failure: show_generic_error,
                params: {
                  source_ids: source_ids.join(","),
                  target_id: target.getAttribute("rel"),
                  relative: this.drop_side, // calculated in onNodeOver
                  csrf: '<?= access::csrf_token() ?>'
                }
              });
              return true;
            }
          });
        }
      },
      multiSelect: true,
      selectedClass: "selected",
      tpl: new Ext.XTemplate(
        '<tpl for=".">',
        '<div class="thumb thumb-{type}" id="thumb-{id}" rel="{id}">',
        '<img src="{thumb_url}" width="{width}" height="{height}" title="{title}">',
        '<div class="icon"></div>',
        '</div>',
        '</tpl>')
    });

    /*
     * ********************************************************************************
     * Toolbar with sort column, sort order and a close button.
     * ********************************************************************************
     */

    sort_order_data = [];
    <? foreach (album::get_sort_order_options() as $key => $value): ?>
    sort_order_data.push(["<?= $key ?>", <?= $value->for_js() ?>]);
    <? endforeach ?>
    var sort_column_combobox = new Ext.form.ComboBox({
      mode: "local",
      editable: false,
      allowBlank: false,
      forceSelection: true,
      triggerAction: "all",
      flex: 3,
      store: new Ext.data.ArrayStore({
        id: 0,
        fields: ["key", "value"],
        data: sort_order_data
      }),
      listeners: {
        "select": function(combo, record, index) {
          set_album_sort({sort_column: record.id});
        }
      },
      valueField: "key",
      displayField: "value"
    });

    var sort_order_combobox = new Ext.form.ComboBox({
      mode: "local",
      editable: false,
      allowBlank: false,
      forceSelection: true,
      triggerAction: "all",
      flex: 2,
      store: new Ext.data.ArrayStore({
        id: 0,
        fields: ["key", "value"],
        data: [
          ["ASC", <?= t("Ascending")->for_js() ?>],
          ["DESC", <?= t("Descending")->for_js() ?>]]
      }),
      listeners: {
        "select": function(combo, record, index) {
          set_album_sort({sort_order: record.id});
        }
      },
      valueField: "key",
      displayField: "value"
    });

    var button_panel = new Ext.Panel({
      layout: "hbox",
      region: "south",
      height: 24,
      layoutConfig: {
        align: "stretch"
      },
      items: [
        {
          xtype: "label",
          cls: "sort",
          flex: 2,
          text: <?= t("Sort order: ")->for_js() ?>
        },
        sort_column_combobox,
        sort_order_combobox,
        {
          xtype: "spacer",
          flex: 10
        }, {
          xtype: "button",
          flex: 2,
          text: <?= t("Close")->for_js() ?>,
          listeners: {
            "click": function() {
              parent.done_organizing(current_album_id);
            }
          }
        }
      ]
    });

    var album_panel = new Ext.Panel({
      layout: "fit",
      region: "center",
      title: <?= t("Drag and drop photos to re-order or move between albums")->for_js() ?>,
      items: [thumb_data_view],
      bbar: button_panel
    });

    /*
     * ********************************************************************************
     *  TreeLoader and TreePanel
     * ********************************************************************************
     */
    var tree_loader = new Ext.tree.TreeLoader({
      dataUrl: '<?= url::site("organize/tree/{$album->id}") ?>',
      nodeParameter: "root_id",
      requestMethod: "post"
    });

    var tree_panel = new Ext.tree.TreePanel({
      useArrows: true,
      autoScroll: true,
      animate: true,
      border: false,
      containerScroll: true,
      enableDD: true,
      dropConfig: {
        appendOnly: true,
        ddGroup: "organizeDD"
      },
      listeners: {
        "click": function(node) {
          load_album_data(node.id);
          if (node.isExpandable() && !node.isExpanded()) {
            node.expand();
          }
        },
        "afterrender": function(v) {
          // Override Ext.tree.TreeDropZone.onNodeOver to change the
          // x-tree-drop-ok-append CSS class to be x-dd-drop-ok since
          // that connotes "ok" instead of "adding something new" and we're
          // moving the item, not adding it.
          //
          // There's probably a better way of overriding the parent method, but
          // my JavaScript-fu is weak.
          v.dropZone.super_onNodeOver = v.dropZone.onNodeOver;
          v.dropZone.onNodeOver = function(target, dd, e, data) {
            var returnCls = this.super_onNodeOver(target, dd, e, data);
            if (returnCls == "x-tree-drop-ok-append") {
              return "x-dd-drop-ok";
            }
            return returnCls;
          }

          // Override Ext.tree.TreeDropZone.getDropPoint so that it allows dropping
          // on any node.  The standard function won't let you drop on leaves, but
          // in our model we consider an album without sub-albums a leaf.
          v.dropZone.getDropPoint = function(e, n, dd) {
            return "append";
          }

          v.dropZone.onNodeDrop = function(target, dd, e, data) {
            var nodes = data.nodes;
            source_ids = [];
            var moving_albums = 0;
            for (var i = 0; i != nodes.length; i++) {
              var node = Ext.fly(nodes[i]);
              source_ids.push(node.getAttribute("rel"));
              moving_albums |= node.hasClass("thumb-album");
            }
            start_busy(<?= t("Moving...")->for_js() ?>);
            Ext.Ajax.request({
              url: '<?= url::site("organize/reparent") ?>',
              method: "post",
              success: function() {
                stop_busy();
                reload_album_data();

                // If we're moving albums around then we need to refresh the tree when we're done
                if (moving_albums) {
                  target.node.reload();

                  // If the target node contains the selected node, then the selected
                  // node just got strafed by the target's reload and no longer exists,
                  // so we can't reload it.
                  var selected_node = v.getNodeById(current_album_id);
                  if (selected_node) {
                    selected_node.reload();
                  }
                }
              },
              failure: show_generic_error,
              params: {
                source_ids: source_ids.join(","),
                target_id: target.node.id,
                csrf: '<?= access::csrf_token() ?>'
              }
            });
            return true;
          }
        }
      },
      loader: tree_loader,

      region: "west",
      split: true,
      minSize: 200,
      maxSize: 350,
      width: 200,

      root: {
        allowDrop: Boolean(<?= access::can("edit", item::root()) ?>),
        nodeType: "async",
        text: "<?= item::root()->title ?>",
        draggable: false,
        id: "<?= item::root()->id ?>",
        expanded: true
      }
    });

    var first_organize_load = true;
    tree_loader.addListener("load", function() {
      if (first_organize_load) {
        tree_panel.getNodeById(<?= $album->id ?>).select();
        load_album_data(<?= $album->id ?>);
        first_organize_load = false;

        // This is a hack that allows us to reload tree nodes asynchronously
        // even though they came with preloaded hierarchical data from the
        // initial tree load.  Without this, any nodes that were preloaded
        // initially won't refresh if you call node.reload() on them.
        tree_loader.doPreload = function() { return false; }
      }
    });
    tree_panel.getRootNode().expand();

    var outer = new Ext.Viewport({
      layout: "border",
      cls: "g-organize",
      items: [tree_panel, album_panel]
    });
  });
</script>
