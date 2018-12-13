/**
 * Boxalino Profiler Management Class
 * It loads current question
 * It allows the user to select options, add answers (text, rating, forms) or skip questions (if allowed)
 * It keeps all selected options set for further processing
 *
 */
var BxProfiler = Class.create();

BxProfiler.prototype = {
    initialize: function (loadUrl, submitUrl, bxRequestUrl, loggedCustomerEvent, visitorEvent, showProgress, choice, questions) {
        this.selectedOptions = {};
        this.bxData = {};
        this.profilerQuestions = questions;
        this.loadUrl = loadUrl;
        this.submitUrl = submitUrl;
        this.bxRequestUrl = bxRequestUrl;
        this.customerEvent = loggedCustomerEvent;
        this.visitorEvent = visitorEvent;
        this.isProgressEnabled = showProgress;
        this.choice = choice;
        this.totalQuestions = this.profilerQuestions.length;
        this.currentStep = 0;
        this.order = 0;
        this.currentQuestion = null;
        this.formId = 'bx-journey-profiler-form';
        this.progressBlockId = 'bx-profiler-progress-wrapper';
        this.skipBlockId = 'bx-question-skip';
        this.backBlockId = 'bx-question-back';
        this.profilerQuestionBlockId = 'bx-profiler-question';
        this.errorBlockId = 'bx-profiler-error';
        this.hasCustomProgress = false;

        if(this.showProgress()) {
            this.showOrHideBlock(this.progressBlockId);
        }

        this.addBxSelect('choice', choice);
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
            case "change":
                this.addSelect(event.target.name, event.target.value);
                let bxProperty = event.target.dataset.bxname;
                if(bxProperty) {
                    this.addBxSelect(event.target.dataset.bxname, event.target.value);
                }
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
                        this.showOrHideBlock(this.errorBlockId);

                        this.order = response.order;
                        this.setCurrentQuestion(response.question);
                        if(this.order>0) {
                            this.hidePreviousQuestion();
                        }
                        this.getQuestionBlock().insert(response.question);
                        this.prepareProgressFlow();
                        this.prepareSkipFlow();
                    }
                }
            }.bind(this)
        });
    },
    hidePreviousQuestion: function() {
        let prevId = +this.order - 1;
        $('bx-profiler-question-'+ prevId).remove();
    },
    setCurrentQuestion: function(question) {
        this.currentQuestion = question;
    },
    addSelect: function(name, value) {
        this.selectedOptions[name] = value;
    },
    addBxSelect: function(name, value) {
        this.bxData[name] = value;
    },
    getCurrentSelects: function() {
        return this.selectedOptions;
    },
    getBxSelects: function() {
        return this.bxData;
    },
    saveAnswer: function() {
        let order = this.order,
            nextOrder = this.getProgress(),
            visualElement = this.getQuestionByStep(nextOrder);
        data = this.getCurrentSelects();
        new Ajax.Request(this.submitUrl, {
            method: 'POST',
            parameters: {
                'bxIndex' : order,
                'bxNextIndex': nextOrder,
                'visualElement': JSON.stringify(visualElement),
                'customer_event': this.customerEvent,
                'visitor_event': this.visitorEvent,
                'data': JSON.stringify(data)
            },
            onSuccess: function (transport) {
                if (transport.responseText.isJSON() === true) {
                    var response = transport.responseText.evalJSON();
                    if (response.question) {
                        this.showOrHideBlock(this.errorBlockId);
                        this.order = response.order;
                        this.setCurrentQuestion(response.question);
                        if(this.order>0) {
                            this.hidePreviousQuestion();
                        }
                        this.getQuestionBlock().insert(response.question);
                        this.prepareProgressFlow();
                        this.prepareSkipFlow();
                    }

                    if (response.error) {
                        $('bx-profiler-error').update();
                        response.error.each(function (message) {
                            var errorMsg = '<div class="bx-profiler-error-msg"><ul><li>' + message
                                + '</li></ul></div>';
                            $('bx-profiler-error').insert(errorMsg);
                        });
                    }
                }
            }.bind(this),
            onComplete: function(transport) {
                this.sendBxRequest();
            }.bind(this)
        });
    },
    sendBxRequest: function() {
        new Ajax.Request(this.bxRequestUrl, {
            method: 'POST',
            parameters: {
                'choice': this.choice,
                'bxData': JSON.stringify(this.bxData),
                'final' : this.isFinalQuestion()
            }
        });
    },
    isFinalQuestion: function() {
        if(this.currentStep >= this.totalQuestions-1) {
            return true;
        }
        return false;
    },
    getQuestionByStep: function(id) {
        if(parseInt(id, 10)<=this.getTotalQuestions()) {
            return this.getQuestions()[id];
        }
    },
    getTotalQuestions: function() {
        return this.totalQuestions;
    },
    addProgressBlockContent: function(html) {
        this.hasCustomProgress = true;
        this.getProgressBlock().insert(html);
    },
    setProgressBlockId: function(blockId) {
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
        this.currentStep = +value + 1;
    },
    showProgress: function() {
        return this.isProgressEnabled;
    },
    prepareProgressFlow: function() {
        this.setProgress(this.order);
        if(this.showProgress() && this.hasCustomProgress === false){
            this.getProgressBlock().update();
            this.getProgressBlock().insert("Question " + this.getProgress() + " of " + this.getTotalQuestions());
        }
    },
    addSkipButtonContent: function(html) {
        this.getSkipBlock().insert(html);
    },
    setSkipBlockId: function(blockId) {
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
        if($('bx-profiler-question-'+ this.order).dataset.submit == 1) {
            this.saveAnswer();
        } else {
            this.loadQuestion(this.getQuestionByStep(this.getProgress()), this.getProgress())
        }
    },
    backQuestion: function() {
        this.showOrHideBlock(this.backBlockId);
        this.loadQuestion(this.getQuestionByStep(this.getProgress()-1), this.getProgress()-1)
    },
    prepareSkipFlow: function() {
        let skipAllowed = $('bx-profiler-question-'+ this.order).dataset.skip;
        if(skipAllowed && (this.currentStep!=this.totalQuestions)) {
            this.showOrHideBlock(this.skipBlockId);
            $(this.skipBlockId).addEventListener("click", this, false);
        }
    },
    prepareBackFlow: function() {
        this.showOrHideBlock(this.backBlockId);
        $(this.backBlockId).addEventListener("click", this, false);
    },
    setFormId: function(formId) {
        this.formId = formId;
    },
    setProfilerBxField: function(id) {
        this.profilerBxField = id;
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
    setErrorBlockId: function (id) {
        this.errorBlockId = id;
    },
    getErrorBlock: function() {
        return $(this.errorBlockId);
    },
    showOrHideBlock: function (block) {
        $(block).toggle();
    }
};