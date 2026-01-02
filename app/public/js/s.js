// on load create a script to load a final script here

function getCookie (name) {
    var dc = document.cookie;
    var cname = name + "=";

    if (dc.length > 0) {
      begin = dc.indexOf(cname);
      if (begin != -1) {
        begin += cname.length;
        end = dc.indexOf(";", begin);
        if (end == -1) end = dc.length;
        return unescape(dc.substring(begin, end));
        }
      }
    return null;
}

// cookies handling
function setCookie(name, value, expires) {
  document.cookie = name + "=" + escape(value) + "; path=/" + ((expires == null) ? "" : "; expires=" + expires.toGMTString());
}

var exp = new Date();
exp.setTime(exp.getTime() + (1000 * 60 * 60 * 24 * 350));


function commentSuccess(data) {
  // ukryj formularz do postowania
  $('#post-comment').hide();
  $('#post-comment textarea').val('');
  
  // pokaż przycisk zapostuj jeszcze raz
  // $('#comment-form-show').prepend('<span class="msg ok">Twój komentarz został dodany!</span> ');
  $('#comment-form-show').show();
  
  //pojaw ładnie nowy komentarz
  var newComment = '<li class="hidden"><span class="br">' + data.content + '</span><span class="secondary">';
  if (data.authorId === null) {
    newComment = newComment + data.author;    
  }
  else 
  {
      newComment = newComment + '<strong><a href="/użytkownik/' + data.author + ',' + data.authorId + '.html">' + data.author + '</a></strong>';
  }
  var now = new Date();
  var hour        = now.getHours();
  if (hour<10) { hour = '0' + hour };
  var minute      = now.getMinutes();
  if (minute<10) { minute = '0' + minute }
  var second      = now.getSeconds();
  if (second<10) { second = '0' + second }
  var monthNumber = now.getMonth() + 1;
  if (monthNumber<10) { monthNumber = '0' + monthNumber }
  var monthDay    = now.getDate();
  if (monthDay<10) { monthDay = '0' + monthDay }
  var year        = now.getFullYear();
  newComment = newComment + ' (' + year + '-' + monthNumber + '-' + monthDay + ' ' + hour + ':' + minute + ':' + second + ')</span>';
  $(newComment).prependTo("#comments ul").fadeIn(2500);
}


$(function(){
  // toggle tracklist additional information
  $("#tracklist span.toggle > a").toggle(
    function () {
      $(this).text("Pokaż szczegóły");
      $("ul.feat").hide();
      setCookie('albumShowDetails', 0, exp);   
    },
    function () {
      $(this).text("Ukryj szczegóły");
      $("ul.feat").show();
      setCookie('albumShowDetails', 1, exp);
    }
  );
  
  // toggle view/hide autoDescription
  $("#description span.toggle > a").toggle(
    function () {
      $(this).text("Ukryj opis standardowy");
      $("p.auto").show();
      setCookie('albumShowAuto', 1, exp);
    },
    function () {
      $(this).text("Pokaż opis standardowy");
      $("p.auto").hide();
      setCookie('albumShowAuto', 0, exp);
    }
  );
  
  $("#q").focus(function(){
    if($(this).text() == "Szukaj...") $(this).text("")
  });

  $('table tr').hover(
     function() {
      $(this).toggleClass('zebra');
     }
  );
  
  // unhide javascript functionality, and hide what can be revelaed using js
  $('.js-visible').show();
  $('.js-hidden').hide();
  
  // read cookies and show/hide auto description
  if (getCookie('albumShowDetails') == 0) { $("#tracklist span.toggle > a").click(); };
  if (getCookie('albumShowAuto') == 1) { $("#description span.toggle > a").click(); };
  
  $('.covers img').tipsy({'html':'true','gravity':'n','delayOut':3000,'delayIn':3000,title: function() { return this.getAttribute('original-title'); }});
  
  // comments
  $('#comment-form-show').click(function(){
    $('#comments form').show();
    $('#comments form textarea').focus();
    $('#comment-form-show').hide();
    return false;
  });

  // submit comments 
  $('#submit').click(function() {
    var dataString = $('#post-comment').serialize();
    $.ajax({
      type: 'POST',
      url: '/comments',
      dataType: 'json',
      data: dataString,
      success: function(data) {
        commentSuccess(data);
      },
      error: function() {
        alert('Problem z dodaniem komentarza, spróbuj za jakiś czas.');
      }
    })
    return false;
  });

  // flag videoclip
  $('#rateDown').click(function() {
    $.ajax({
      type: 'POST',
      url: '/api/songs/flag-video',
      success: function(data) {
        $('#downCount').text(parseInt($('#downCount').text())+1);
      },
      error: function() {
        alert('Problem ze zgłoszeniem, spróbuj za jakiś czas.');
      }
    })
    
    return false;
  });
});