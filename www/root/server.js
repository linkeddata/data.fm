// Generated by CoffeeScript 1.6.3
(function() {
  var rpc;

  rpc = function(method, ev) {
    return $.ajax("api/" + method + ".php", {
      contentType: "application/json",
      data: JSON.stringify(ev.data),
      type: "POST",
      dataType: "json",
      success: function(data, textStatus, xhr) {
	console.log(data.response);
	//data = JSON.parse(data);
        return ev.source.postMessage(data, ev.origin);
      },
      error: function(error) {
        console.log(error);
      }
    });
  };

  window.addEventListener("message", function(ev) {
    return rpc.call(this, ev.data.method, ev);
  });

}).call(this);
