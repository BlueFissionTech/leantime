leantime.widgetController = (function () {
    var grid = [];
    var gridEventsBound = false;

    // Helper function to find next available position
    var findAvailablePosition = function(widget, grid) {
        let x = widget.gridX || 0;
        let y = widget.gridY || 0;
        let w = widget.gridWidth || 2;
        let h = widget.gridHeight || 2;
        let columns = typeof grid.getColumn === 'function' ? grid.getColumn() : 12;
        let maxX = Math.max(0, columns - w);

        // Try the preferred position first
        if (grid.willItFit({x: x, y: y, w: w, h: h})) {
            return { x: x, y: y };
        }

        // If preferred position is occupied, find next available spot
        let nodes = (grid.engine && Array.isArray(grid.engine.nodes)) ? grid.engine.nodes : [];
        let maxY = nodes.reduce(function(max, node) {
            return Math.max(max, (node.y || 0) + (node.h || 0));
        }, 0);

        // Try positions from top to bottom
        for (let newY = 0; newY <= maxY + 1; newY++) {
            for (let newX = 0; newX <= maxX; newX++) {
                if (grid.willItFit({x: newX, y: newY, w: w, h: h})) {
                    return { x: newX, y: newY };
                }
            }
        }
        return { x: 0, y: maxY + 1 }; // Fallback to bottom
    };

    // Implement safe HTML rendering callback
    GridStack.renderCB = function(el, w) {
        if (w.content) {
            // Using DOMPurify to sanitize content if available
            if (typeof DOMPurify !== 'undefined') {
                el.innerHTML = DOMPurify.sanitize(w.content);
            }
        }
    };

    var bindGridActions = function () {
        if (gridEventsBound) {
            return;
        }

        jQuery(document)
            .off("click.leantimeWidget", ".grid-stack-item .removeWidget")
            .on("click.leantimeWidget", ".grid-stack-item .removeWidget", function(e) {
                e.preventDefault();
                removeWidget(jQuery(this).closest(".grid-stack-item")[0]);
            });

        jQuery(document)
            .off("click.leantimeWidget", ".grid-stack-item .fitContent")
            .on("click.leantimeWidget", ".grid-stack-item .fitContent", function(e) {
                e.preventDefault();
                resizeWidget(jQuery(this).closest(".grid-stack-item")[0]);
            });

        gridEventsBound = true;
    };


    var initGrid = function () {
        grid = GridStack.init({
            margin: '0px 15px 15px 0px',
            handle: ".grid-handler-top",
            minRow: 2,
            cellHeight: '30px',
            float: true,
            draggable: {
                handle: '.grid-handler-top',
                appendTo: 'body',
                // scroll: true,
                // scrollSensitivity: 20,
                // scrollSpeed: 10
            },
            lazyLoad: false,
            columnOpts: {
                breakpointForWindow: true,  // test window vs grid size
                breakpoints: [{w:700, c:1},{w:950, c:6}]
            },
        });

        grid.on('dragstop', function(event, item) {
            saveGrid();
        });

        grid.on('resizestop', function(Event, item) {
            saveGrid();
        });

        bindGridActions();

        jQuery(document).ready(function(){
            jQuery("#gridBoard").css("opacity", 1);
        });

    };

    var saveGrid = function() {
        let items = [];
        let nodes = (grid.engine && Array.isArray(grid.engine.nodes)) ? grid.engine.nodes.slice() : [];

        // Sort items by Y position first, then X position
        nodes.sort((a, b) => {
            return a.y === b.y ? a.x - b.x : a.y - b.y;
        });

        let visibilityData = null;

        if(arguments.length > 0 && arguments[0].action === "toggleWidget") {
            visibilityData = {
                widgetId: arguments[0].widgetId,
                visible: arguments[0].visible
            };
        }

        nodes.forEach(function(node) {
            let htmxElement = node.el ? node.el.querySelector("[hx-get]") : null;

            if (!htmxElement) {
                return;
            }

            items.push({
                id: htmxElement.getAttribute("id"),
                widgetUrl: htmxElement.getAttribute("hx-get"),
                widgetTrigger: htmxElement.getAttribute("hx-trigger"),
                gridX: node.x != undefined ? node.x : 0,
                gridY: node.y != undefined ? node.y : 0,
                gridWidth: node.w != undefined ? node.w : 1,
                gridHeight: node.h != undefined ? node.h : 1
            });
        });


        jQuery.post(leantime.appUrl+"/widgets/widgetManager",
            {
                action: "saveGrid",
                data: items,
                visibilityData: visibilityData
            },
            function(data, status){
            });
    };


    var removeWidget = function (el) {
        if (!el) {
            return;
        }

        let htmxElement = el.querySelector("[hx-get]");
        let widgetId = htmxElement ? htmxElement.getAttribute("id") : null;

        grid.removeWidget(el, true);

        if (widgetId) {
            jQuery("#widget-toggle-" + widgetId).prop("checked", false);
        }

        saveGrid();
    }

    var resizeWidget = function (el) {
        let grid = document.querySelector('.grid-stack').gridstack;
        grid.resizeToContent(el, false);
        saveGrid();
    }

    var toggleWidgetVisibility = function(id, element, widget) {
        let grid = document.querySelector('.grid-stack').gridstack;
        let visible = jQuery(element).is(":checked");

        // Find the next available position
        let position = findAvailablePosition(widget, grid);

        if (!visible) {
            removeWidget(jQuery("#" + id).closest(".grid-stack-item")[0]);
        } else {
            // Create the widget structure using DOM methods
            const widgetNode = document.createElement('div');
            widgetNode.className = 'grid-stack-item';
            widgetNode.id = 'widget_wrapper_' + widget.id;
            widgetNode.setAttribute('gs-x', position.x);
            widgetNode.setAttribute('gs-y', position.y);
            widgetNode.setAttribute('gs-w', widget.gridWidth || 2);
            widgetNode.setAttribute('gs-h', widget.gridHeight || 2);
            widgetNode.setAttribute('gs-min-w', widget.gridMinWidth || 1);
            widgetNode.setAttribute('gs-min-h', widget.gridMinHeight || 1);

            // Create the content container
            const contentDiv = document.createElement('div');
            contentDiv.className = `grid-stack-item-content tw-p-none ${
                widget.widgetBackground == "default" ? "maincontentinner" : widget.widgetBackground
            }`;

            // Set the inner structure
            contentDiv.innerHTML = buildWidget(widget);
            widgetNode.appendChild(contentDiv);

            // Add to grid and make it a widget
            grid.el.appendChild(widgetNode);
            grid.makeWidget(widgetNode);

            // Initialize HTMX
            htmx.process(widgetNode);

            saveGrid({action: "toggleWidget", widgetId: id, visible: visible});
        }
    }

    var buildWidget = function(widget) {
        return '<div class="tw-flex tw-flex-col tw-h-full ' + (widget.widgetBackground == "default" ? "tw-pb-l" : "") + '">' +
            '            <div class="stickyHeader" style="padding:15px; height:50px;  width:100%;">\n' +
            '               <div class="grid-handler-top tw-h-[40px] tw-cursor-grab tw-float-left tw-mr-sm">\n' +
            '                    <i class="fa-solid fa-grip-vertical"></i>\n' +
            '                </div>\n' +
            '           ' + (widget.name != '' ? '<h5 class="subtitle tw-pb-m tw-float-left tw-mr-sm">' + widget.name + '</h5>' : '') + '\n' +
            '            <div class="inlineDropDownContainer tw-float-right">\n' +
            '                <a href="javascript:void(0);" class="dropdown-toggle ticketDropDown editHeadline" data-toggle="dropdown">\n' +
            '                    <i class="fa fa-ellipsis-v" aria-hidden="true"></i>\n' +
            '                </a>\n' +
            '                <ul class="dropdown-menu">\n' +
            '                    <li><a href="javascript:void(0)" class="fitContent"><i class="fa-solid fa-up-right-and-down-left-from-center"></i> Resize to fit content</a></li>\n' +
            '                        <li><a href="javascript:void(0)" class="removeWidget"><i class="fa fa-eye-slash"></i> Hide</a></li>\n' +
            '                </ul>\n' +
            '            </div>\n' +
            '\n' +
            '        </div>\n' +
            '        <span class="clearall"></span>\n' +
            ' <div class="widgetContent ' + (widget.widgetBackground == "default" ? 'tw-px-m' : '') + '">\n' +
            '             <div hx-get="'+widget.widgetUrl+'" hx-trigger="'+widget.widgetTrigger+'" id="'+widget.id+'" class="tw-h-full"></div>\n' +
            '        </div>\n' +
            '       </div>\n' +
            '        <div class="clear"></div>\n';
    }

    // Make public what you want to have public, everything else is private
    return {
        resizeWidget: resizeWidget,
        removeWidget: removeWidget,
        saveGrid: saveGrid,
        initGrid:initGrid,
        toggleWidgetVisibility:toggleWidgetVisibility
    };
})();
