/**
 * Created by Mirza on 16/07/2016.
 * To define Passport Strategy - we are currently using 'local' strategy
 */

var passport = require('passport');
var LocalStrategy = require('passport-local').Strategy;
var mongoose = require('mongoose');
var User = mongoose.model('User');


