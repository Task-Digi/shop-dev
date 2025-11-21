

function questionsucess(e) {
    // console.log(e);
    var questionsuccess = $(e).attr('data-child');
    // console.log(questionsuccess);
    $('[data-child=' + questionsuccess + ']').addClass('ButtonClickedgreen');
    $(e).next().removeClass('ButtonClickedred');
    $(e).next().next().removeClass('ButtonClickedyellow');
    let parent = $('[data-child=' + questionsuccess + ']').parents(".card").data().parent;
    let value = $('[data-child=' + questionsuccess + ']').data().value;
    $('#input'+parent).attr('value', value);
    $(e).parents(".card").removeClass('errorCard');
    requiredCheckWithoutMessage();
}

function questiondanger(e) {
    var questiondanger = $(e).attr('data-child');
    // console.log(questiondanger);
    $('[data-child=' + questiondanger + ']').addClass('ButtonClickedred');
    $(e).prev().removeClass('ButtonClickedgreen');
    $(e).next().removeClass('ButtonClickedyellow');
    let parent = $('[data-child=' + questiondanger + ']').parents(".card").data().parent;
    let value = $('[data-child=' + questiondanger + ']').data().value;
    $('#input'+parent).attr('value', value);
    $(e).parents(".card").removeClass('errorCard');
    requiredCheckWithoutMessage();
}

function questionwarning(e) {
    var questionwarning = $(e).attr('data-child');
    // console.log(questionwarning);
    $('[data-child=' + questionwarning + ']').addClass('ButtonClickedyellow');
    $(e).prev().removeClass('ButtonClickedred');
    $(e).prev().prev().removeClass('ButtonClickedgreen');
    let parent = $('[data-child=' + questionwarning + ']').parents(".card").data().parent;
    let value = $('[data-child=' + questionwarning + ']').data().value;
    $('#input'+parent).attr('value', value);
    $(e).parents(".card").removeClass('errorCard');
    requiredCheckWithoutMessage();
}

function requiredCheck() {
    let count = 0;
    $('.ques .card-req .card input').each(function(i, item) {
        let value = $(this).val();
        $(this).parents('.card').removeClass('errorCard');
        if(value === '' || value === null || value === undefined) {
            if(count === null || count === '' || count === undefined) count = 1;
            else count += 1;
            $(this).parents('.card').addClass('errorCard');
            // $(this).parents('.card').next('.errorDetails').empty().text('This Question is Required.').removeClass('d-none');
        }
        else {
            let parent = $(this).parents('.card').data().parent;
            // $(this).parents('.card').next('.errorDetails').empty().addClass('d-none');
            data[parent] = value;
        }
    });
    if(count !== 0) $('#firstSub').attr('disabled', true);
    else $('#firstSub').removeAttr('disabled');
    console.log(count);
    return count;
}

function requiredCheckWithoutMessage() {
    let count = 0;
    $('.ques .card-req .card input').each(function(i, item) {
        let value = $(this).val();
        if(value === '' || value === null || value === undefined) {
            // if($(this).parents().hasClass('card') && $(this).parents().hasClass('errorCard') ) {
            //     $(this).parents('.card').next('.errorDetails').empty().text('This Question is Required.').removeClass('d-none');
            // }
            if(count === null || count === '' || count === undefined) count = 1;
            else count += 1;
        }
        else {
            let parent = $(this).parents('.card').data().parent;
            // if($(this).parents().hasClass('card') && $(this).parents().hasClass('errorCard') ) {
            //     $(this).parents('.card').next('.errorDetails').empty().addClass('d-none');
            // }
            data[parent] = value;
        }
    });
    if(count !== 0) $('#firstSub').attr('disabled', true);
    else $('#firstSub').removeAttr('disabled');
    return count;
}

function isEmail(email) {
    // var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
    var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,6})+$/;
    console.log(regex);
    // var regex = (?:[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*|"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-z0-9-]*[a-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\]);
    return regex.test(email);
}

function isNumber(number) {
    var regex = /^(0|[1-9]\d*)$/;
    console.log(regex);
    return regex.test(number);
}

function errorMessage(id, status, message = '') {
    if(status === true) {
        $(id).addClass('error');
        console.log($(id).parent().next('.errorDetails'));
        $(id).next('.errorDetails').empty().text(message).removeClass('d-none');
    }
    else {
        $(id).removeClass('error');
        $(id).next().filter('small.errorDetails').empty().addClass('d-none');
    }
}

function saveQuestions(pageUrlOne, pageUrlTwo) {
    let count = requiredCheck();
    console.log(count);
    if (count === 0) {
        let last = $('#inputqu100001').val();
        if(last !== '' && last !== null && last !== undefined) data['qu100001'] = last;

        last = $('#inputqu100003').val();
        if(last !== '' && last !== null && last !== undefined) data['qu100003'] = last;
        $.ajax({
            type: 'POST',
            url: pageUrlOne,
            data,
            success: function (response) {
                console.log(response);
                window.location.replace(pageUrlTwo);
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.log(textStatus, errorThrown);
                // window.location.replace(pageUrlTwo);
            }
        });
    } else {
        $(this).attr('disabled', true);
    }
}

function ajaxMethod(url, data, type = 'GET') {
    $.ajax({
        type: type,
        url: url,
        data,
        success: function (response) {
            console.log(response, response.status, response.redirectUrl);
            if(response.status === true)
                window.location.replace(response.redirectUrl);
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log(textStatus, errorThrown);
            // if(response.status === true)
            //     window.location.replace(response.redirectUrl);
        }
    });
}
