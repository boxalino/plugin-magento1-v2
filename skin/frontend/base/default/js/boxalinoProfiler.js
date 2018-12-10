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
        this.selectedOptions = [];
        this.profilerQuestions = questions;
        this.loadUrl = loadUrl;
        this.submitUrl = submitUrl;
        this.customerEvent = loggedCustomerEvent;
        this.visitorEvent = visitorEvent;
        this.isProgressEnabled = showProgress;
        this.totalQuestions = this.profilerQuestions.length;
        this.currentStep = 0;
        this.order = 0;
        this.currentQuestion = null;
        this.formId = 'bx-journey-profiler-form';
        this.progressBlockId = 'bx-profiler-progress-wrapper';
        this.skipBlockId = 'bx-question-skip';
        this.backBlockId = 'bx-question-back';
        this.profilerQuestionBlockId = 'bx-profiler-question';

        if(this.showProgress()) {
            this.showOrHideBlock(this.progressBlockId);
        }

        this.loadQuestion(this.profilerQuestions[this.order], this.order);
        this.initProfilerEvents();
    },
    getQuestions: function() {
        return this.profilerQuestions;
    },
    initProfilerEvents: function() {
        $(this.formId).addEventListener("load", this, false);
        $(this.formId).addEventListener("change", this, false);
        $(this.formId).addEventListener("submit", this, false);
    },
    handleEvent:function(event) {
        switch(event.type) {
            case "load":
                console.log("load");
                break;
            case "change":
                this.addSelect(event.target.name, event.target.value);
                break;
            case "submit":
                event.preventDefault();
                this.saveAnswer();
                break;
            case "click":
                if(event.path[1].id == this.skipBlockId){
                    this.skipQuestion();
                }
                if(event.path[1].id == this.backBlockId){
                    this.backQuestion();
                }
                break;
            default:
                return;
        }
    },
    loadQuestion: function(question, order) {
        this.showOrHideBlock('bx-profiler-error');

        new Ajax.Request(this.loadUrl, {
            method: 'post',
            parameters: {
                'visualElement': JSON.stringify(question),
                'bxIndex' : order
            },
            onSuccess: function (transport) {
                if (transport.responseText.isJSON() === true) {
                    var response = transport.responseText.evalJSON();
                    if (response.question) {
                        this.setCurrentQuestion(question);
                        if(order>0) {
                            this.hidePreviousQuestion(order);
                            //this.prepareBackFlow(order);
                        }
                        this.getQuestionBlock().insert(response.question);
                        this.prepareSkipFlow(order);
                        this.prepareProgressFlow(order);
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
    addListener: function(id, options) {
    },
    hidePreviousQuestion: function(id) {
        let prevId = id-1;
        $('bx-profiler-question-'+ prevId).remove();
    },
    setCurrentQuestion: function(question) {
        this.currentQuestion = question;
    },
    addSelect: function(name, value) {
        this.selectedOptions[name] = value;
    },
    getCurrentSelects: function() {
        return this.selectedOptions;
    },
    saveAnswer: function() {
        this.showOrHideBlock('bx-profiler-error');
        let order = this.order,
            nextOrder = this.getProgress(),
            visualElement = this.getQuestionByStep(nextOrder);
        new Ajax.Request(this.submitUrl, {
            method: 'POST',
            parameters: {
                'bxIndex' : order,
                'bxNextIndex': nextOrder,
                'visualElement': JSON.stringify(visualElement),
                'customer_event': this.customerEvent,
                'visitor_event': this.visitorEvent,
                'data': this.getCurrentSelects()
            },
            onSuccess: function (transport) {
                if (transport.responseText.isJSON() === true) {
                    var response = transport.responseText.evalJSON();
                    if (response.question) {
                        this.setCurrentQuestion(response.question);
                        if(response.order>0) {
                            this.hidePreviousQuestion(response.order);
                        }
                        this.getQuestionBlock().insert(response.question);
                        this.prepareSkipFlow(response.order);
                        this.prepareProgressFlow(response.order);
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
    getQuestionByStep: function(id) {
        if(parseInt(id, 10)<=this.getTotalQuestion()) {
            return this.getQuestions()[id];
        }
    },
    getTotalQuestion: function() {
        return this.totalQuestions;
    },
    addProgressBlockContent: function(html) {
        this.getProgressBlock().insert(html);
    },
    setProgressBlockId: function(blockId)
    {
        this.progressBlockId = blockId;
        if(this.showProgress()) {
            this.showOrHideBlock(this.progressBlockId());
        }
    },
    getProgressBlock: function() {
        return $(this.progressBlockId);
    },
    getProgress: function() {
        return this.currentStep;
    },
    setProgress: function(value) {
        this.currentStep = value+1;
    },
    showProgress: function() {
        return this.isProgressEnabled;
    },
    prepareProgressFlow: function(order) {
        this.setProgress(order);
        if(this.showProgress()){
            this.getProgressBlock().update();
            this.getProgressBlock().insert("progress " + this.getProgress() + " of " + this.getTotalQuestion());
        }
    },
    addSkipButtonContent: function(html) {
        this.getSkipBlock().insert(html);
    },
    setSkipBlockId: function(blockId)
    {
        this.skipBlockId = blockId;
        if(this.showProgress()) {
            this.showOrHideBlock(this.skipBlockId());
        }
    },
    getSkipBlock: function() {
        return $(this.skipBlockId);
    },
    skipQuestion: function() {
        this.showOrHideBlock(this.skipBlockId);
        this.loadQuestion(this.getQuestionByStep(this.getProgress()), this.getProgress())
    },
    backQuestion: function() {
        this.showOrHideBlock(this.backBlockId);
        this.loadQuestion(this.getQuestionByStep(this.getProgress()-1), this.getProgress()-1)
    },
    prepareSkipFlow: function(order) {
        let skipAllowed = $('bx-profiler-question-'+ order).dataset.skip;
        if(skipAllowed) {
            this.showOrHideBlock(this.skipBlockId);
            $(this.skipBlockId).addEventListener("click", this, false);
        }
    },
    prepareBackFlow: function(order) {
        this.showOrHideBlock(this.backBlockId);
        $(this.backBlockId).addEventListener("click", this, false);
    },
    setFormId: function(formId) {
        this.formId = formId;
    },
    getFormBlock: function() {
        return $(this.formId);
    },
    setQuestionBlockId: function (id) {
        this.profilerQuestionBlockId = id;
    },
    getQuestionBlock: function() {
        return $(this.profilerQuestionBlockId);
    },
    showOrHideBlock: function (block) {
        $(block).toggle();
    }
};