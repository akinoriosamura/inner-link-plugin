(function($) {
    // 非表示のdivからJSONデータを取得
    var dataset = JSON.parse($('#show-article-map-dataset').text());
    var nodedata = dataset[0];
    var edgedata = dataset[1];
    var nodes = new vis.DataSet(nodedata);
    var edges = new vis.DataSet(edgedata);
    var container = document.getElementById('mynetwork');
    var data = { nodes: nodes, edges: edges };
    var options = {
      nodes: { shape: "box" },
      edges: { arrows: { to: { enabled: true, scaleFactor: 1, type: 'arrow' } } },
      manipulation: { enabled: true }
    };
    var network = new vis.Network(container, data, options);
  
    network.on('doubleClick', function(e) {
      var nodeID = e.nodes.toString();
      var url = $(data.nodes.get(nodeID).title).attr('href');
      window.open(url, '_blank');
    });
  
    $('#searchnodebutton').on('click', function() {
      var search = $('#searchnodequery').val();
      // ノードラベルで検索
      var hitNodes = nodes.get({
        filter: function(item) {
          var label = item.label.replace("\\r\\n", "");
          return label.indexOf(search) !== -1;
        }
      });
      var hitNodeIDs = [];
      for (var i = 0; i < hitNodes.length; i++) {
        hitNodeIDs.push(hitNodes[i].id);
      }
      // ノードを選択
      network.selectNodes(hitNodeIDs);
    });
    $('#searchnodequery').keypress(function(e) {
      if (e.which === 13) { // Enterキー押下時
        $('#searchnodebutton').click();
      }
    });
  
    // グループリストの初期化
    var groupList = nodes.distinct('group').sort();
    for (var i = 0; i < groupList.length; i++) {
      $('#groupList').append('<input type="checkbox" name="visibleGroups" value="' + groupList[i] + '" checked="checked" style="margin-left:15px;">' + groupList[i]);
    }
  
    // グループ毎のノードデータを準備
    var nodeGroups = [];
    for (var i = 0; i < groupList.length; i++) {
      nodeGroups[groupList[i]] = nodes.get({ filter: function(item) { return item.group === groupList[i]; } });
    }
  
    // グループの表示・非表示切替
    $('#groupList>input').on('change', function() {
      var currentGroupNames = nodes.distinct('group');
      var visibleGroupNames = [];
      $("#groupList :checkbox:checked").each(function() {
        visibleGroupNames.push(this.value);
      });
      var diffGroupNames = diffArray(currentGroupNames, visibleGroupNames);
      if (currentGroupNames.length < visibleGroupNames.length) {
        for (var i = 0; i < diffGroupNames.length; i++) {
          nodes.add(nodeGroups[diffGroupNames[i]]);
        }
      } else if (currentGroupNames.length > visibleGroupNames.length) {
        for (var i = 0; i < diffGroupNames.length; i++) {
          nodes.remove(nodeGroups[diffGroupNames[i]]);
        }
      }
    });
  
    function diffArray(arr1, arr2) {
      return arr1.concat(arr2).filter(item => !arr1.includes(item) || !arr2.includes(item));
    }
  
    // 物理演算のON/OFF切替
    $('#togglepBlur').on('click', function() {
      var physicsEnabled = network.physics.options.enabled;
      var buttonText = physicsEnabled ? "Start" : "Stop";
      network.setOptions({ physics: { enabled: !physicsEnabled } });
      $(this).text(buttonText);
    });
  
    // CSVダウンロード処理
    $('#downloadCSV').on('click', function() {
      var csv = 'fromID, fromURL, fromGroup, fromTitle, toID, toURL, toGroup, toTitle\n';
      edges.forEach(function(e) {
        var fromNode = nodes.get(e['from']);
        var toNode = nodes.get(e['to']);
        csv += fromNode['id'] + ',' +
               fromNode['title'].match(/href="(.+?)"/)[1] + ',' +
               fromNode['group'] + ',' +
               fromNode['label'].replace(/\n/g, '') + ',' +
               toNode['id'] + ',' +
               toNode['title'].match(/href="(.+?)"/)[1] + ',' +
               toNode['group'] + ',' +
               toNode['label'].replace(/\n/g, '') + '\n';
      });
      var blob = new Blob([csv], { "type": "text/plain" });
      if (window.navigator.msSaveBlob) {
        window.navigator.msSaveBlob(blob, "ShowArticleMap.csv");
        window.navigator.msSaveOrOpenBlob(blob, "ShowArticleMap.csv");
      } else {
        $('#downloadCSV').attr('href', window.URL.createObjectURL(blob));
      }
    });
  })(jQuery);
  