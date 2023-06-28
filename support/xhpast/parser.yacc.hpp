/* A Bison parser, made by GNU Bison 2.3.  */

/* Skeleton interface for Bison's Yacc-like parsers in C

   Copyright (C) 1984, 1989, 1990, 2000, 2001, 2002, 2003, 2004, 2005, 2006
   Free Software Foundation, Inc.

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2, or (at your option)
   any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software
   Foundation, Inc., 51 Franklin Street, Fifth Floor,
   Boston, MA 02110-1301, USA.  */

/* As a special exception, you may create a larger work that contains
   part or all of the Bison parser skeleton and distribute that work
   under terms of your choice, so long as that work isn't itself a
   parser generator using the skeleton or a modified version thereof
   as a parser skeleton.  Alternatively, if you modify or redistribute
   the parser skeleton itself, you may (at your option) remove this
   special exception, which will cause the skeleton and the resulting
   Bison output files to be licensed under the GNU General Public
   License without this special exception.

   This special exception was added by the Free Software Foundation in
   version 2.2 of Bison.  */

/* Tokens.  */
#ifndef YYTOKENTYPE
# define YYTOKENTYPE
   /* Put the tokens into the symbol table, so that GDB and other debuggers
      know about them.  */
   enum yytokentype {
     T_REQUIRE_ONCE = 258,
     T_REQUIRE = 259,
     T_EVAL = 260,
     T_INCLUDE_ONCE = 261,
     T_INCLUDE = 262,
     T_LOGICAL_OR = 263,
     T_LOGICAL_XOR = 264,
     T_LOGICAL_AND = 265,
     T_PRINT = 266,
     T_SR_EQUAL = 267,
     T_SL_EQUAL = 268,
     T_XOR_EQUAL = 269,
     T_OR_EQUAL = 270,
     T_AND_EQUAL = 271,
     T_MOD_EQUAL = 272,
     T_CONCAT_EQUAL = 273,
     T_DIV_EQUAL = 274,
     T_MUL_EQUAL = 275,
     T_MINUS_EQUAL = 276,
     T_PLUS_EQUAL = 277,
     T_COALESCE = 278,
     T_BOOLEAN_OR = 279,
     T_BOOLEAN_AND = 280,
     T_SPACESHIP = 281,
     T_IS_NOT_IDENTICAL = 282,
     T_IS_IDENTICAL = 283,
     T_IS_NOT_EQUAL = 284,
     T_IS_EQUAL = 285,
     T_IS_GREATER_OR_EQUAL = 286,
     T_IS_SMALLER_OR_EQUAL = 287,
     T_SR = 288,
     T_SL = 289,
     T_INSTANCEOF = 290,
     T_UNSET_CAST = 291,
     T_BOOL_CAST = 292,
     T_OBJECT_CAST = 293,
     T_ARRAY_CAST = 294,
     T_BINARY_CAST = 295,
     T_UNICODE_CAST = 296,
     T_STRING_CAST = 297,
     T_DOUBLE_CAST = 298,
     T_INT_CAST = 299,
     T_DEC = 300,
     T_INC = 301,
     T_CLONE = 302,
     T_NEW = 303,
     T_EXIT = 304,
     T_IF = 305,
     T_ELSEIF = 306,
     T_ELSE = 307,
     T_ENDIF = 308,
     T_LNUMBER = 309,
     T_DNUMBER = 310,
     T_STRING = 311,
     T_STRING_VARNAME = 312,
     T_VARIABLE = 313,
     T_NUM_STRING = 314,
     T_INLINE_HTML = 315,
     T_CHARACTER = 316,
     T_BAD_CHARACTER = 317,
     T_ENCAPSED_AND_WHITESPACE = 318,
     T_CONSTANT_ENCAPSED_STRING = 319,
     T_BACKTICKS_EXPR = 320,
     T_ECHO = 321,
     T_DO = 322,
     T_WHILE = 323,
     T_ENDWHILE = 324,
     T_FOR = 325,
     T_ENDFOR = 326,
     T_FOREACH = 327,
     T_ENDFOREACH = 328,
     T_DECLARE = 329,
     T_ENDDECLARE = 330,
     T_AS = 331,
     T_SWITCH = 332,
     T_ENDSWITCH = 333,
     T_CASE = 334,
     T_DEFAULT = 335,
     T_BREAK = 336,
     T_CONTINUE = 337,
     T_GOTO = 338,
     T_FUNCTION = 339,
     T_CONST = 340,
     T_RETURN = 341,
     T_TRY = 342,
     T_CATCH = 343,
     T_THROW = 344,
     T_USE = 345,
     T_GLOBAL = 346,
     T_PUBLIC = 347,
     T_PROTECTED = 348,
     T_PRIVATE = 349,
     T_FINAL = 350,
     T_ABSTRACT = 351,
     T_STATIC = 352,
     T_VAR = 353,
     T_UNSET = 354,
     T_ISSET = 355,
     T_EMPTY = 356,
     T_HALT_COMPILER = 357,
     T_CLASS = 358,
     T_INTERFACE = 359,
     T_EXTENDS = 360,
     T_IMPLEMENTS = 361,
     T_OBJECT_OPERATOR = 362,
     T_DOUBLE_ARROW = 363,
     T_LIST = 364,
     T_ARRAY = 365,
     T_CLASS_C = 366,
     T_METHOD_C = 367,
     T_FUNC_C = 368,
     T_LINE = 369,
     T_FILE = 370,
     T_COMMENT = 371,
     T_DOC_COMMENT = 372,
     T_OPEN_TAG = 373,
     T_OPEN_TAG_WITH_ECHO = 374,
     T_OPEN_TAG_FAKE = 375,
     T_CLOSE_TAG = 376,
     T_WHITESPACE = 377,
     T_START_HEREDOC = 378,
     T_END_HEREDOC = 379,
     T_HEREDOC = 380,
     T_DOLLAR_OPEN_CURLY_BRACES = 381,
     T_CURLY_OPEN = 382,
     T_PAAMAYIM_NEKUDOTAYIM = 383,
     T_BINARY_DOUBLE = 384,
     T_BINARY_HEREDOC = 385,
     T_NAMESPACE = 386,
     T_NS_C = 387,
     T_DIR = 388,
     T_NS_SEPARATOR = 389,
     T_INSTEADOF = 390,
     T_CALLABLE = 391,
     T_TRAIT = 392,
     T_TRAIT_C = 393,
     T_YIELD = 394,
     T_FINALLY = 395,
     T_ELLIPSIS = 396
   };
#endif
/* Tokens.  */
#define T_REQUIRE_ONCE 258
#define T_REQUIRE 259
#define T_EVAL 260
#define T_INCLUDE_ONCE 261
#define T_INCLUDE 262
#define T_LOGICAL_OR 263
#define T_LOGICAL_XOR 264
#define T_LOGICAL_AND 265
#define T_PRINT 266
#define T_SR_EQUAL 267
#define T_SL_EQUAL 268
#define T_XOR_EQUAL 269
#define T_OR_EQUAL 270
#define T_AND_EQUAL 271
#define T_MOD_EQUAL 272
#define T_CONCAT_EQUAL 273
#define T_DIV_EQUAL 274
#define T_MUL_EQUAL 275
#define T_MINUS_EQUAL 276
#define T_PLUS_EQUAL 277
#define T_COALESCE 278
#define T_BOOLEAN_OR 279
#define T_BOOLEAN_AND 280
#define T_SPACESHIP 281
#define T_IS_NOT_IDENTICAL 282
#define T_IS_IDENTICAL 283
#define T_IS_NOT_EQUAL 284
#define T_IS_EQUAL 285
#define T_IS_GREATER_OR_EQUAL 286
#define T_IS_SMALLER_OR_EQUAL 287
#define T_SR 288
#define T_SL 289
#define T_INSTANCEOF 290
#define T_UNSET_CAST 291
#define T_BOOL_CAST 292
#define T_OBJECT_CAST 293
#define T_ARRAY_CAST 294
#define T_BINARY_CAST 295
#define T_UNICODE_CAST 296
#define T_STRING_CAST 297
#define T_DOUBLE_CAST 298
#define T_INT_CAST 299
#define T_DEC 300
#define T_INC 301
#define T_CLONE 302
#define T_NEW 303
#define T_EXIT 304
#define T_IF 305
#define T_ELSEIF 306
#define T_ELSE 307
#define T_ENDIF 308
#define T_LNUMBER 309
#define T_DNUMBER 310
#define T_STRING 311
#define T_STRING_VARNAME 312
#define T_VARIABLE 313
#define T_NUM_STRING 314
#define T_INLINE_HTML 315
#define T_CHARACTER 316
#define T_BAD_CHARACTER 317
#define T_ENCAPSED_AND_WHITESPACE 318
#define T_CONSTANT_ENCAPSED_STRING 319
#define T_BACKTICKS_EXPR 320
#define T_ECHO 321
#define T_DO 322
#define T_WHILE 323
#define T_ENDWHILE 324
#define T_FOR 325
#define T_ENDFOR 326
#define T_FOREACH 327
#define T_ENDFOREACH 328
#define T_DECLARE 329
#define T_ENDDECLARE 330
#define T_AS 331
#define T_SWITCH 332
#define T_ENDSWITCH 333
#define T_CASE 334
#define T_DEFAULT 335
#define T_BREAK 336
#define T_CONTINUE 337
#define T_GOTO 338
#define T_FUNCTION 339
#define T_CONST 340
#define T_RETURN 341
#define T_TRY 342
#define T_CATCH 343
#define T_THROW 344
#define T_USE 345
#define T_GLOBAL 346
#define T_PUBLIC 347
#define T_PROTECTED 348
#define T_PRIVATE 349
#define T_FINAL 350
#define T_ABSTRACT 351
#define T_STATIC 352
#define T_VAR 353
#define T_UNSET 354
#define T_ISSET 355
#define T_EMPTY 356
#define T_HALT_COMPILER 357
#define T_CLASS 358
#define T_INTERFACE 359
#define T_EXTENDS 360
#define T_IMPLEMENTS 361
#define T_OBJECT_OPERATOR 362
#define T_DOUBLE_ARROW 363
#define T_LIST 364
#define T_ARRAY 365
#define T_CLASS_C 366
#define T_METHOD_C 367
#define T_FUNC_C 368
#define T_LINE 369
#define T_FILE 370
#define T_COMMENT 371
#define T_DOC_COMMENT 372
#define T_OPEN_TAG 373
#define T_OPEN_TAG_WITH_ECHO 374
#define T_OPEN_TAG_FAKE 375
#define T_CLOSE_TAG 376
#define T_WHITESPACE 377
#define T_START_HEREDOC 378
#define T_END_HEREDOC 379
#define T_HEREDOC 380
#define T_DOLLAR_OPEN_CURLY_BRACES 381
#define T_CURLY_OPEN 382
#define T_PAAMAYIM_NEKUDOTAYIM 383
#define T_BINARY_DOUBLE 384
#define T_BINARY_HEREDOC 385
#define T_NAMESPACE 386
#define T_NS_C 387
#define T_DIR 388
#define T_NS_SEPARATOR 389
#define T_INSTEADOF 390
#define T_CALLABLE 391
#define T_TRAIT 392
#define T_TRAIT_C 393
#define T_YIELD 394
#define T_FINALLY 395
#define T_ELLIPSIS 396




#if ! defined YYSTYPE && ! defined YYSTYPE_IS_DECLARED
typedef int YYSTYPE;
# define yystype YYSTYPE /* obsolescent; will be withdrawn */
# define YYSTYPE_IS_DECLARED 1
# define YYSTYPE_IS_TRIVIAL 1
#endif



/* @generated */
