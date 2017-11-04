
$(document).ready(function () {
  $('#pdp_download').each(function (index, element) {
    $(this).off('click').on('click', function (e) {
      e.preventDefault();
      var reload=document.getElementById("pdp_reload_download").href;
      pdpintegration.downloadPdpDesign(element.href,reload);
    });
  });
});
var pdpintegration = {
  downloadPdpDesign: function (zipUrl,reloadUrl) {
    var currentUrl = window.location.href;
    var reload = reloadUrl+'&force-update-svg=1';
    reload += '&return-uri=' + encodeURIComponent(currentUrl);
    $.ajax({
      url: zipUrl,
      type: 'GET',
      success: function (res)
      {
        var jsonData = res;
        if (typeof jsonData === 'object')
        {

        }
        else
        {
          jsonData = JSON.parse(res);
        }
        var dataRes = jsonData.data;
        var fileZip = dataRes.file;
        var baseUrl = dataRes.baseUrl;
        window.location.href = baseUrl + fileZip;
      },
      error: function (res)
      {
        var mResponseText = res.responseText;
        if (typeof mResponseText == 'object')
        {

        }
        else
        {
          mResponseText = JSON.parse(mResponseText);
        }
        console.log(mResponseText);
        if (mResponseText.errorCode && mResponseText.errorCode === 15)
        {
            if (confirm('We need create it again from Design Editor, Editor.Press OK then just wait, all done automatically !.'))
            {
              window.location.href = reload;
            }
        } else {
        }
      }
    })
  },
}
