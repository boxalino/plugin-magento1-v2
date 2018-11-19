/**
 * Boxalino Profiler Management Class
 * It loads current question
 * It allows the user to select options, add answers (text, rating, forms) or skip questions (if allowed)
 * It keeps all selected options set for further processing
 *
 */
var BxProfiler = Class.create();

BxProfiler.prototype = {
    initialize: function (loadUrl, submitUrl,loggedCustomerEvent, visitorEvent, showProgress, questions) {
        this.selectedOptions = new Array();
        this.profilerQuestions = JSON.parse(questions);
        this.loadUrl = loadUrl;
        this.submitUrl = submitUrl;
        this.customerEvent = loggedCustomerEvent;
        this.visitorEvent = visitorEvent;
        this.isProgressEnabled = showProgress;
        this.totalQuestions = this.profilerQuestions.length;
        this.currentStep = 0;
        this.currentQuestion = null;

        console.log(this.profilerQuestions);
        if(this.showProgress()) {
            this.showOrHideBlock("bx-profiler-progress-wrapper");
        }

        this.loadQuestion(this.profilerQuestions[this.currentStep], this.currentStep);

    },
    getQuestions: function() {
        return this.profilerQuestions;
    },
    loadQuestion: function(question, step) {
        console.log(question);

        this.showOrHideBlock('bx-profiler-error');

        new Ajax.Request(this.loadUrl, {
            method: 'post',
            parameters: {
                'visualElement': JSON.stringify(question),
                'bxIndex' : step
            },
            onSuccess: function (transport) {
                if (transport.responseText.isJSON() === true) {
                    var response = transport.responseText.evalJSON();
                    if (response.question) {
                        this.setCurrentQuestion(question);
                        $('bx-profiler-question').insert(response.question);
                        var skipAllowed = $('bx-profiler-question-'+step).dataset.skip;
                        if(skipAllowed === true) {
                            this.showOrHideBlock('bx-question-button-skip-'+step);
                        }
                        this.showOrHideBlock('bx-profiler-error');
                        this.setProgress(step);
                        if(this.showProgress()){
                            //update step
                        }
                    }

                    if (response.error) {
                        response.error.each(function (message) {
                            if (Array.isArray(message) || typeof(message) === 'string') {
                                var errorMsg = '<li class="bx-profiler-error-msg"><ul><li>' + message
                                    + '</li></ul></li>';
                                $('bx-profiler-error').insert(errorMsg);
                            } else {
                                $H(message).each(function (pair) {
                                    var errorMsg = '<li class="bx-profiler-error-msg"><ul><li>' + pair.value
                                        + '</li></ul></li>';
                                    $('bx-profiler-error').insert(errorMsg);
                                });
                            }
                        });
                    }
                }
            }.bind(this)
        });
    },
    setCurrentQuestion: function(question) {
        this.currentQuestion = question;
    },
    getQuestionOptions: function() {
        console.log("get question options");
    },
    isSelected: function() {
        console.log("is selected");
    },
    addSelect: function(id) {
        console.log("add select");
    },
    removeSelect: function() {
        console.log("remove select");
    },
    getCurrentSelects: function() {
        console.log("get current selects");
        return this.selectedOptions;
    },
    saveAnswer: function() {
        this.showOrHideBlock('bx-profiler-error');
        var step = this.getProgress(),
            nextStep = step + 1,
            visualElement = this.getQuestionByStep(nextStep);
        new Ajax.Request(this.submitUrl, {
            method: 'post',
            parameters: {
                'bxIndex' : step,
                'bxNextIndex': nextStep,
                'visualElement': JSON.stringify(visualElement),
                'customer_event': this.customerEvent,
                'visitor_event': this.visitorEvent,
                'data': this.selectedOptions
            },
            onSuccess: function (transport) {
                if (transport.responseText.isJSON() === true) {
                    var response = transport.responseText.evalJSON();
                    if (response.question) {
                        this.setCurrentQuestion(visualElement);
                        $('bx-profiler-question').insert(response.question);
                        var skipAllowed = $('bx-profiler-question-'+ response.step).dataset.skip;
                        if(skipAllowed === true) {
                            this.showOrHideBlock('bx-question-button-skip-'+ response.step);
                        }
                        this.showOrHideBlock('bx-profiler-error');
                        this.setProgress(response.step);
                        if(this.showProgress()){
                            //update step
                        }
                    }

                    if (response.error) {
                        response.error.each(function (message) {
                            if (Array.isArray(message) || typeof(message) === 'string') {
                                var errorMsg = '<li class="bx-profiler-error-msg"><ul><li>' + message
                                    + '</li></ul></li>';
                                $('bx-profiler-error').insert(errorMsg);
                            } else {
                                $H(message).each(function (pair) {
                                    var errorMsg = '<li class="bx-profiler-error-msg"><ul><li>' + pair.value
                                        + '</li></ul></li>';
                                    $('bx-profiler-error').insert(errorMsg);
                                });
                            }
                        });
                    }
                }
            }.bind(this)
        });
    },
    addSkipButton: function(html) {
        $('bx-question-button-skip-'+ this.getProgress()).insert(html);
    },
    skipQuestion: function(id, attributeCode) {
        //set empty value for the attributeCode
        this.showOrHideBlock('bx-question-button-skip-'+ id);
        this.loadQuestion(this.getQuestionByStep(id+1), id+1)
    },
    getQuestionByStep: function(id) {
        if(parseInt(id, 10)<=this.totalQuestions) {
            return this.getQuestions()[id];
        }
    },
    addFieldListener: function() {

    },
    getProgress: function() {
        return this.currentStep;
    },
    setProgress: function(value) {
        this.currentStep = value;
    },
    showProgress: function() {
        return this.isProgressEnabled;
    },
    showOrHideBlock: function (block) {
        $(block).toggle();
    }
};
