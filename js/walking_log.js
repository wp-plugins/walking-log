var wrsWalkingLog =
{
  initialize: function()
  {
    // initialize properties
    this.editMode = false;
    this.editingRow = false;
    this.selectedDate = '';


    // remove elements that are no longer needed since we have javascript support
    jQuery('#wrswl-select-date').remove();
    jQuery('#wrswl-monthly-data').empty();

    // remove edit and delete links from inline edit/delete hrefs    
    jQuery('.wrswl-edit-inline').removeAttr('href');
    jQuery('.wrswl-delete-inline').removeAttr('href');
    
    // make edit buttons visible
    jQuery('#wrswl-edit-log-top').show();
    jQuery('#wrswl-edit-log-bottom').show();
    
    // link up events
    var thisInstance = this;
    jQuery('#wrswl-month-select').bind('change', function(event) { thisInstance.monthChanged(event); });
    jQuery('#wrswl-edit-log-top').bind('click', function(event) { thisInstance.toggleEditMode(event); });
    jQuery('#wrswl-edit-log-bottom').bind('click', function(event) { thisInstance.toggleEditMode(event); });
    
    
    // initial load for the selected month (controlled by the PHP code, which selects the current month)
    this.monthChanged();
  },


  debugMsg: function(message)
  {
    jQuery('#trace_message').append(message + '<br />');
  },
  
  
  extractId: function(id)
  {
    var parts = id.split('-');
    return parseInt(parts[parts.length - 1], 10);
  },
  

  // from: http://snippets.dzone.com/posts/show/2099
  daysInMonth: function(year, month)
  {
    return parseInt(32 - new Date(year, month, 32).getDate(), 10);
  },
  

  getCell: function(item, cellIndex)
  {
    return jQuery('td:nth-child(' + cellIndex + ')', item);
  },


  parseNumber: function(value)
  {
    var vs = value + '';  // force to string type
    
    // change decimal point to a .
    vs = vs.replace(wrsWalkingLogSettings.decimalPoint, '.');
    
    var result = parseFloat(vs);
    if (isNaN(result))
    {
      result = 0.0;
    }
    
    return result;
  },
  
  
  // this should only be called with a number formatted with "." for decimal point and "," for grouping
  formatNumber: function(value, places)
  {
    var vs = value.toFixed(places) + '';  // force to string type
    
    // remove grouping
    vs = vs.replace(new RegExp(',', 'g'), '');
    
    // change decimal point
    vs = vs.replace('.', wrsWalkingLogSettings.decimalPoint);
    
    return vs;
  },
    

  timeToMinutes: function(time)
  {
    var result = 0, parts;
    
    parts = time.split(':');
    
    // just minutes
    if (parts.length === 1)
    {
      result = this.parseNumber(parts[0]);
    }
      
    // minutes:seconds
    else if (parts.length === 2)
    {
      result = this.parseNumber(parts[0]) +
               this.parseNumber(parts[1]) / 60.0;
    }

    // hours:minutes:seconds               
    else if (parts.length === 3)
    {
      result = this.parseNumber(parts[0]) * 60.0 +
               this.parseNumber(parts[1]) +
               this.parseNumber(parts[2]) / 60.0;
    }         
    else
    {
      result = time;
    }
    
    return result;
  },


  minutesToHHMMSS: function(minutes)
  {
    var seconds, hours;
    
        
    seconds = minutes * 60;
    
    hours = Math.floor(seconds / 3600);
    seconds %= 3600;
    
    minutes = Math.floor(seconds / 60);
    seconds %= 60;

    seconds = Math.round(seconds);

    minutes += '';
    if (minutes.length < 2)
    {
      minutes = '0' + minutes;
    }

    seconds += '';
    if (seconds.length < 2)
    {
      seconds = '0' + seconds;
    }


    return hours + ':' + minutes + ':' + seconds;
  },


  milesToKilometers: function(miles)
  {
    return (miles * 1.609344);
  },


  kilometersToMiles: function(kilometers)
  {
    return (kilometers / 1.609344);
  },


  disableEditControls: function()
  {
    jQuery('#wrswl-month-select, #wrswl-edit-log-top, #wrswl-edit-log-bottom, #wrswl-add-log-top, #wrswl-add-log-bottom').attr('disabled', true);
  },

  
  enableEditControls: function()
  {
    jQuery('#wrswl-month-select, #wrswl-edit-log-top, #wrswl-edit-log-bottom, #wrswl-add-log-top, #wrswl-add-log-bottom').removeAttr('disabled');
  },

  
  updateTotals: function()
  {
    var rows, totalRow, totalTime, totalDistance, thisInstance;

    thisInstance = this;
    

    // get all table rows, excluding the header and the last row which is the total
    rows = jQuery('#wrswl-monthly-data tr:gt(0)').not('tr:last-child');
    
    // sum time
    totalTime = 0;
    rows.children('td:nth-child(2)').each(function()
    {
      totalTime += thisInstance.timeToMinutes(jQuery(this).html());  // thisInstance.parseNumber(jQuery(this).html());
    });
    
    // sum distance
    totalDistance = 0;
    rows.children('td:nth-child(3)').each(function()
    {
      totalDistance += thisInstance.parseNumber(jQuery(this).html());
    });
    
    
    // place the new values in the last row
    totalRow = jQuery('#wrswl-monthly-data tr:last-child');

    if (wrsWalkingLogSettings.timeFormat === 'minutes')
    {  
      this.getCell(totalRow, 2).html(this.formatNumber(totalTime, 2)); // totalTime.toFixed(2));
    }
    else
    {
      this.getCell(totalRow, 2).html(this.minutesToHHMMSS(totalTime));
    }


    this.getCell(totalRow, 3).html(this.formatNumber(totalDistance, 2)); // .totalDistance.toFixed(2));
  },
  
  
  buildDateEdit: function(item) 
  {
    var dateComponents, daysInMonth, currentId, result, optionId, i;
    
    // get year and month
    dateComponents = this.selectedDate.split('-');  // y, m, d
    daysInMonth = this.daysInMonth(dateComponents[0], dateComponents[1] - 1);  // month is 0 based
    

    // get the currently selected item: type-id-x
    currentId = this.extractId(item.attr('id'));

    if (currentId === 0)
    {
      var today = new Date();
      currentId = today.getDate(); 
    }


    result = '';

    for (i = 0; i < daysInMonth; i+=1)
    {
      optionId = i + 1;
      
      result += '<option ';

      if (optionId === currentId)
      {
        result += 'selected ';
      }
        
      result += 'value="' + optionId + '" id="date-opt-' + optionId + '">' + optionId + '</option>';
    }

    result = '<select class="wrswl-date-edit">' + result + '</select>';
    result = '<td class="wrswl-date-edit">' + result + '</td>';

    return result;
  },


  buildTimeEdit: function(item) 
  {
    return '<td class="wrswl-time-edit"><input class="wrswl-time-edit" type="text" id="row_time" name="row_time" value="' +
              item.html() + '" /></td>';
  },


  buildDistanceEdit: function(item)
  {
    return '<td class="wrswl-distance-edit"><input class="wrswl-distance-edit" type="text" name="row_distance" value="' +
              item.html() + '" /></td>';
  },


  buildTypeEdit: function(item)
  {
    var types, currentId, result, optionId, i;
  
    types = wrsWalkingLogSettings.exerciseTypes;

    // get the currently selected item: type-id-x
    currentId = this.extractId(item.attr('id'));
    result = '';

    for (i = 0; i < types.length; i+=1)
    {
      optionId = types[i].id;

      result += '<option ';

      if (optionId === currentId)
      {
        result += 'selected ';
      }

      result += 'value="' + optionId + '" id="type-opt-' + optionId + '">' + types[i].name + '</option>';
    }

    result = '<select class="wrswl-type-edit">' + result + '</select>';
    result = '<td class="wrswl-type-edit">' + result + '</td>';

    return result;
  },


  buildLocationEdit: function(logId, item)
  {
    var locations, currentId, result, optionId, i, buttons;
    
    locations = wrsWalkingLogSettings.exerciseLocations;

    // get the currently selected item: location-id-x
    currentId = this.extractId(item.attr('id'));
    result = '';

    for (i = 0; i < locations.length; i+=1)
    {
      optionId = locations[i].id; // i + 1;

      result += '<option ';

      if (optionId === currentId)
      {
        result += 'selected ';
      }

      result += 'value="' + optionId + '" id="location-opt-' + optionId + '">' + locations[i].name + '</option>';
    }

    result = '<select class="wrswl-location-edit">' + result + '</select>';

    buttons = '<div style="text-align:right"><input style="margin-top:8px;margin-bottom:4px;margin-right:4px" id="save-' + logId + '" type="button" value="Save" />' +
              '<input style="margin-top:8px;margin-bottom:4px;margin-right:4px" id="cancel-' + logId + '" type="button" value="Cancel" /></div>';


    result = '<td class="wrswl-location-edit">' + result +
               '  <div>' + buttons + '</div>' +
             '</td>';

    return result;
  },


  addRow: function()
  {
    var html, newItem;

    html = '<tr id="row-0">' +
           '<td class="wrswl-date-row" id="date-id-0">0</td>' +
           '<td class="wrswl-time-row">0' + wrsWalkingLogSettings.decimalPoint + '00</td>' +
           '<td class="wrswl-distance-row">0' + wrsWalkingLogSettings.decimalPoint + '00</td>' +
           '<td class="wrswl-type-row" id="type-id-' + wrsWalkingLogSettings.exerciseTypes[0].id + '">' + 
                      wrsWalkingLogSettings.exerciseTypes[0].name + '</td>' +
           '<td class="wrswl-location-row" id="location-id-' + wrsWalkingLogSettings.exerciseLocations[0].id + '">' +
              '<div>' + wrsWalkingLogSettings.exerciseLocations[0].name + '</div>' +
              '<div class="wrswl-rowactions">' +
                '<a class="wrswl-edit-inline" href="#">edit</a> | ' +
                '<a class="wrswl-delete-inline" href="#">delete</a></div></td></tr>';


    // insert a new item at the top - first-child gives us the header row, so the first data row is directly after
    jQuery('#wrswl-monthly-data tr:first-child').after(html);

    // find the inserted item and set it to edit mode
    newItem = jQuery('#row-0');

    // hide row actions on the new item        
    jQuery('.wrswl-rowactions', newItem).hide();

    // set up row for editing
    this.editItem(newItem);
  },


  editItem: function(item)
  {
    var thisInstance, logId, fields;
    
    
    thisInstance = this;
    
    // get log ID
    logId = this.extractId(item.attr('id'));
    
    // we're now editing a row    
    this.editingRow = true;
    
    // don't want to allow changing months or other editing functions during row editing
    this.disableEditControls();
    
    // hide the display row
    item.hide();


    // set up and insert the html for the editor row
    fields = this.buildDateEdit(jQuery('td:nth-child(1)', item)) +
             this.buildTimeEdit(jQuery('td:nth-child(2)', item)) +
             this.buildDistanceEdit(jQuery('td:nth-child(3)', item)) +
             this.buildTypeEdit(jQuery('td:nth-child(4)', item)) +
             this.buildLocationEdit(logId, jQuery('td:nth-child(5)', item));
                 
    item.after('<tr id="editor-' + logId + '">' + fields + '</tr>');

    
    // bind save and cancel events, and focus on the first field    
    jQuery('#save-' + logId).bind('click', function(event) { thisInstance.saveChanges(event); });
    jQuery('#cancel-' + logId).bind('click', function(event) { thisInstance.cancelChanges(event); });
    jQuery('#row_time').focus();
  },
  
  
  editRow: function(event)
  {
    // identify the parent row element and enable editing
    this.editItem(jQuery(event.target).closest('tr'));
  },


  deleteRow: function(event)
  {
    var thisInstance, item, logId;
    
    thisInstance = this;

    // find the parent row element and extract the log ID
    item = jQuery(event.target).closest('tr');
    logId = this.extractId(item.attr('id'));


    // temporarily hide the item - we'll remove it if the delete succeeds, or show it again if it fails
    item.hide();

    // create status item...
    item.after('<tr id="status-' + logId + '"><td colspan="5">' + wrsWalkingLogSettings.deletingMsg + '</td></tr>');


    // execute the delete
    jQuery.ajax({
       type: 'POST',
       url: wrsWalkingLogSettings.admin_url,
       data: { action: 'delete_log', 
               row_id: logId,
               nonce: jQuery('input#wrswl_nonce').val(),
               action_id: jQuery('input#wrswl_id').val()
             },
       
       success: function(data)
       {
         if (data.substr(0, 2) === 'OK')
         {
           // remove hidden item
           item.remove();

           // remove status item
           jQuery('#status-' + logId).remove();
            
           // re-summarize totals
           thisInstance.updateTotals();
         }
         else
         {
           thisInstance.debugMsg(data);

           // show hidden item
           item.show();

           // remove status item
           jQuery('#status-' + logId).remove();
           
        }
      },
        
       
      error: function()
      {
        // some very poor error handling, replace status with new message
        jQuery('#status-' + logId).html('<td colspan="5">' + wrsWalkingLogSettings.errorMsg + '</td>').fadeIn('fast');
      }
    });
  },


  cancelChanges: function(event) 
  {
    var item, logId;
    
    
    // find the parent row element and extract the log ID - note that this is giving us the editor element
    item = jQuery(event.target).closest('tr');
    logId = this.extractId(item.attr('id'));

    // we're no longer editing a row    
    this.editingRow = false;

    // allow editing functions again
    this.enableEditControls();

    // remove the editor row
    item.remove();
    
    // now find the hidden row element and show or remove it
    item = jQuery('#wrswl-monthly-data #row-' + logId);
    
    // if we cancelled a new row then remove the row item, otherwise show the original
    if (logId === 0)
    {
      item.remove();
    }
    else
    {
      item.show();
    }

    // hide edit/delete links    
    jQuery('.wrswl-rowactions').stop(true, true).hide();
  },


  saveChanges: function(event)
  {
    var thisInstance, item, logId, updateAction, dateValue, dateSelected, timeValue,
        distanceValue, typeValue, typeSelected, locationValue, locationSelected, dateComponents, dateText,
        parts, dow, d, currentId;
        
    thisInstance = this;
  
    // find the parent row element and extract the log ID - note that this is giving us the editor row element
    item = jQuery(event.target).closest('tr');
    logId = this.extractId(item.attr('id'));

    // we're no longer editing a row    
    this.editingRow = false;


    // determine insert/update action based on id
    updateAction = 'update_log';
    if (logId === 0)
    {
      updateAction = 'insert_log';
    }

    // extract the user-entered data
    dateValue = jQuery('select', this.getCell(item, 1)).val();
    dateSelected = jQuery('select :selected', this.getCell(item, 1));
    
    timeValue = jQuery('input', this.getCell(item, 2)).val();
    timeValue = this.timeToMinutes(timeValue);
    
    distanceValue = jQuery('input', this.getCell(item, 3)).val();
    distanceValue = this.parseNumber(distanceValue);

    // convert distance to miles if it's being entered in kilometers,
    // since everything will be stored in miles
    if (wrsWalkingLogSettings.distanceFormat !== 'miles')
    {
      distanceValue = this.kilometersToMiles(distanceValue);
    }
    
    typeValue = jQuery('select', this.getCell(item, 4)).val();
    typeSelected = jQuery('select :selected', this.getCell(item, 4));
    
    locationValue = jQuery('select', this.getCell(item, 5)).val();
    locationSelected = jQuery('select :selected', this.getCell(item, 5));

    dateComponents = this.selectedDate.split('-');
    dateText = dateComponents[0] + '/' + dateComponents[1] + '/' + dateValue;
        
    
    
    // we're done with the editor element, find the row element that's used to display the data - this was
    // hidden at the time the editor element was created
    item.remove();
    item = jQuery('#wrswl-monthly-data #row-' + logId);


    // create status item after the item we're editing
    // the edited item remains hidden until the ajax request completes, after which
    // this status item will be removed
    item.after('<tr id="status-' + logId + '"><td colspan="5">' + wrsWalkingLogSettings.updatingMsg + '</td></tr>');

    
    jQuery.ajax({
       type: 'POST',
       url: wrsWalkingLogSettings.admin_url,
       data: { 
         action: updateAction,  
         row_id: logId,
         log_date: dateText,
         elapsed_time: timeValue,
         distance: distanceValue,
         type_id: typeValue,
         location_id: locationValue,
         nonce: jQuery('input#wrswl_nonce').val(),
         action_id: jQuery('input#wrswl_id').val()
       },
       
       success: function(data)
       {
         // allow editing functions again
         thisInstance.enableEditControls();

         // remove status item - we have to do this before we grab the new log id (in the case of an insert) since
         // we'll be reassigning it to the new id - i.e. logId was 0 on the status item if this was a new row, so
         // we have to delete status-0 before we overwrite logId with the new id from the database insert
         jQuery('#status-' + logId).remove();

           

         if (data.substr(0, 2) === 'OK')
         {
           // if this was an insert then grab the new log id
           parts = data.split(':');
           if (parts[1] !== '0')
           {
             logId = parts[1];
             item.attr('id', 'row-' + logId);
  
             // this is a new row, so we have to bind the row events for editing
             thisInstance.bindRowEvents(item);
           }
         
           // ----- set the values on the display row ----- //
           
           // date
           dow = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
           d = new Date(dateText);
           thisInstance.getCell(item, 1).html(dateSelected.text() + ' - ' + dow[d.getDay()]);
           currentId = thisInstance.extractId(dateSelected.attr('id'));
           thisInstance.getCell(item, 1).attr('id', 'date-id-' + currentId);
           

           // time - convert to display format
           if (wrsWalkingLogSettings.timeFormat !== 'minutes')
           {
             timeValue = thisInstance.minutesToHHMMSS(timeValue);
           }
           else
           {
             timeValue = thisInstance.formatNumber(timeValue, 2); // timeValue.toFixed(2);
           }

           thisInstance.getCell(item, 2).html(timeValue);

           // distance - convert to display format
           if (wrsWalkingLogSettings.distanceFormat !== 'miles')
           {
             distanceValue = thisInstance.milesToKilometers(distanceValue);
           }
           thisInstance.getCell(item, 3).html(thisInstance.formatNumber(distanceValue, 2)); // distanceValue.toFixed(2));
           
           // type
           thisInstance.getCell(item, 4).html(typeSelected.text());
           currentId = thisInstance.extractId(typeSelected.attr('id'));
           thisInstance.getCell(item, 4).attr('id', 'type-id-' + currentId);
  
           // location
           jQuery('div:eq(0)', thisInstance.getCell(item, 5)).html(locationSelected.text());
           currentId = thisInstance.extractId(locationSelected.attr('id'));
           thisInstance.getCell(item, 5).attr('id', 'location-id-' + currentId);
  
  
           // show the hidden item now that it's modified
           item.show();
  
           // re-summarize totals
           thisInstance.updateTotals();
  
           // hide the edit links
           jQuery('.wrswl-rowactions', item).hide();
         }
         else 
         {
           thisInstance.debugMsg(data);
           
           // allow editing functions again
           thisInstance.enableEditControls();
  
           // show hidden item, leaving the old values intact since the update failed
           item.show();
  
           // remove status item
           jQuery('#status-' + logId).remove();
         }
       },    // success
         
       error: function()
       {
         // some very poor error handling, replace status with new message
         jQuery('#status-' + logId).html('<td colspan="5">' + wrsWalkingLogSettings.errorMsg + '</td>').fadeIn('fast');
       }
     });
  },


  bindRowEvents: function(element)
  {
    var thisInstance = this;
    
    // add mouseenter, mouseleave events
    element.hover(
      function() 
      {
        if (!thisInstance.editingRow)
        {
          jQuery(this).find('.wrswl-rowactions').stop(true, true).fadeIn('fast');
        }
      },
    
      function()
      {
        if (!thisInstance.editingRow)
        {
          jQuery(this).find('.wrswl-rowactions').stop(true, true).hide();
        }
      }
    );


    // add inline edit events
    jQuery('.wrswl-edit-inline', element).click(function(event)
    {
      thisInstance.editRow(event);
      event.preventDefault();
    });
      

    // add inline delete events
    jQuery('.wrswl-delete-inline', element).click(function(event)
    {
      thisInstance.deleteRow(event);
      event.preventDefault();
    });
  },


  enableEditMode: function() 
  {
    var thisInstance = this;

    // add "new log entry" button, if we're not already editing - this can be called when we're already in
    // edit mode if the user has selected a new month, so only make the changes if we're not already in edit mode
    if (!this.editMode)
    {
      this.editMode = true;
      
      // add the button
      jQuery('#wrswl-edit-log-top').after('<input type="button" id="wrswl-add-log-top" href="#" value="New Log Entry" />');
      jQuery('#wrswl-edit-log-bottom').after('<input type="button" id="wrswl-add-log-bottom" href="#" value="New Log Entry" />');

      // bind the click event to add a new row
      jQuery('#wrswl-add-log-top, #wrswl-add-log-bottom').click(function(event) 
      {
        thisInstance.addRow();
        event.preventDefault();
      });
    }


    // add events to each row in the table, except totals      
    jQuery('#wrswl-monthly-data tr:not(:last-child)').each(function(index, element)
    {
      thisInstance.bindRowEvents(jQuery(element));
    });
  },


  disableEditMode: function()
  {
    this.editMode = false;

    // remove the "new log entry" button
    jQuery('#wrswl-add-log-top, #wrswl-add-log-bottom').remove();

    // remove all events - have to do tr events, and descendents separately - seems like that used to
    // work with just the first selector in previous versions of jQuery - should test that some day
    jQuery('#wrswl-monthly-data tr, #wrswl-monthly-data tr *').unbind();
  },


  toggleEditMode: function(event)
  {
    var item = jQuery('#wrswl-edit-log-top, #wrswl-edit-log-bottom');

    if (!this.editMode) 
    {
      item.attr('value', 'Stop Editing');
      this.enableEditMode();
    }
    else 
    {
      item.attr('value', 'Edit Log');
      this.disableEditMode();
    }
 
    event.preventDefault();
  },


  loadDataForSelectedDate: function()
  {
    var thisInstance = this;
    
    jQuery('#wrswl-monthly-data').html('<p><br />' + wrsWalkingLogSettings.loadingMsg + '</p>').show();

    // we're no longer editing a row
    this.editingRow = false;
    
    jQuery.ajax({
      type: 'GET',
      url: wrsWalkingLogSettings.admin_url,
      data: { action: 'get_log', 
              date: this.selectedDate, 
              random: Math.random(),
              nonce: jQuery('input#wrswl_nonce').val(),
              action_id: jQuery('input#wrswl_id').val()
            }, // random is to prevent caching
       
      success: function(data)
      {
        if (data.substr(0, 2) === 'OK')
        {
          data = data.substr(3);
        
          jQuery('#wrswl-monthly-data').fadeOut(0, function()
          {
            // show the new table, hide row actions, set up the header and footer class
            jQuery('#wrswl-monthly-data').html(data).fadeIn('fast');
            jQuery('#wrswl-monthly-data tr .wrswl-rowactions').hide();
            jQuery('#wrswl-monthly-data th, #wrswl-monthly-data tr:last-child').addClass('wrswl-table-header-footer');
  
            // enable edit mode on the new table rows if needed             
            if (thisInstance.editMode)
            {
              thisInstance.enableEditMode();
            }
          });
        }
        else
        {
          thisInstance.debugMsg(data);
          
          // some very poor error handling
          jQuery('#wrswl-monthly-data').html(wrsWalkingLogSettings.errorMsg).fadeIn('fast');
        }
      },
        
      error: function()
      {
        // some very poor error handling
        jQuery('#wrswl-monthly-data').html(wrsWalkingLogSettings.errorMsg).fadeIn('fast');
      }
    });
  },
  

  monthChanged: function(event)
  {
    var thisInstance = this;
    this.selectedDate = jQuery('#wrswl-month-select').val();

    jQuery('#wrswl-monthly-data').fadeOut('fast', function()
    {
      thisInstance.loadDataForSelectedDate();
    });
  }
};


// initialize object when the document is ready
jQuery(document).ready(function()
{
  wrsWalkingLog.initialize();
});
