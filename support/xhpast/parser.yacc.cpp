/* A Bison parser, made by GNU Bison 2.3.  */

/* Skeleton implementation for Bison's Yacc-like parsers in C

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

/* C LALR(1) parser skeleton written by Richard Stallman, by
   simplifying the original so-called "semantic" parser.  */

/* All symbols defined below should begin with yy or YY, to avoid
   infringing on user name space.  This should be done even for local
   variables, as they might otherwise be expanded by user macros.
   There are some unavoidable exceptions within include files to
   define necessary library symbols; they are noted "INFRINGES ON
   USER NAME SPACE" below.  */

/* Identify Bison output.  */
#define YYBISON 1

/* Bison version.  */
#define YYBISON_VERSION "2.3"

/* Skeleton name.  */
#define YYSKELETON_NAME "yacc.c"

/* Pure parsers.  */
#define YYPURE 1

/* Using locations.  */
#define YYLSP_NEEDED 0

/* Substitute the variable and function names.  */
#define yyparse xhpastparse
#define yylex   xhpastlex
#define yyerror xhpasterror
#define yylval  xhpastlval
#define yychar  xhpastchar
#define yydebug xhpastdebug
#define yynerrs xhpastnerrs


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




/* Copy the first part of user declarations.  */
#line 1 "parser.y"

/*
 * If you modify this grammar, please update the version number in
 * ./xhpast.cpp and libphutil/src/parser/xhpast/bin/xhpast_parse.php
 */

#include "ast.hpp"
#include "node_names.hpp"
// PHP's if/else rules use right reduction rather than left reduction which
// means while parsing nested if/else's the stack grows until it the last
// statement is read. This is annoying, particularly because of a quirk in
// bison.
// http://www.gnu.org/software/bison/manual/html_node/Memory-Management.html
// Apparently if you compile a bison parser with g++ it can no longer grow
// the stack. The work around is to just make your initial stack ridiculously
// large. Unfortunately that increases memory usage while parsing which is
// dumb. Anyway, putting a TODO here to fix PHP's if/else grammar.
#define YYINITDEPTH 500
#line 21 "parser.y"

#undef yyextra
#define yyextra static_cast<yy_extra_type*>(xhpastget_extra(yyscanner))
#undef yylineno
#define yylineno yyextra->first_lineno
#define push_state(s) xhp_new_push_state(s, (struct yyguts_t*) yyscanner)
#define pop_state() xhp_new_pop_state((struct yyguts_t*) yyscanner)
#define set_state(s) xhp_set_state(s, (struct yyguts_t*) yyscanner)

#define NNEW(t) \
  (new xhpast::Node(t))

#define NTYPE(n, type) \
  ((n)->setType(type))

#define NMORE(n, end) \
  ((n)->expandRange(end))

#define NSPAN(n, type, end) \
  (NMORE(NTYPE((n), type), end))

#define NEXPAND(l, n, r) \
  ((n)->expandRange(l)->expandRange(r))

using namespace std;

static void yyerror(void* yyscanner, void* _, const char* error) {
  if (yyextra->terminated) {
    return;
  }
  yyextra->terminated = true;
  yyextra->error = error;
}



/* Enabling traces.  */
#ifndef YYDEBUG
# define YYDEBUG 0
#endif

/* Enabling verbose error messages.  */
#ifdef YYERROR_VERBOSE
# undef YYERROR_VERBOSE
# define YYERROR_VERBOSE 1
#else
# define YYERROR_VERBOSE 1
#endif

/* Enabling the token table.  */
#ifndef YYTOKEN_TABLE
# define YYTOKEN_TABLE 0
#endif

#if ! defined YYSTYPE && ! defined YYSTYPE_IS_DECLARED
typedef int YYSTYPE;
# define yystype YYSTYPE /* obsolescent; will be withdrawn */
# define YYSTYPE_IS_DECLARED 1
# define YYSTYPE_IS_TRIVIAL 1
#endif



/* Copy the second part of user declarations.  */


/* Line 216 of yacc.c.  */
#line 451 "parser.yacc.cpp"

#ifdef short
# undef short
#endif

#ifdef YYTYPE_UINT8
typedef YYTYPE_UINT8 yytype_uint8;
#else
typedef unsigned char yytype_uint8;
#endif

#ifdef YYTYPE_INT8
typedef YYTYPE_INT8 yytype_int8;
#elif (defined __STDC__ || defined __C99__FUNC__ \
     || defined __cplusplus || defined _MSC_VER)
typedef signed char yytype_int8;
#else
typedef short int yytype_int8;
#endif

#ifdef YYTYPE_UINT16
typedef YYTYPE_UINT16 yytype_uint16;
#else
typedef unsigned short int yytype_uint16;
#endif

#ifdef YYTYPE_INT16
typedef YYTYPE_INT16 yytype_int16;
#else
typedef short int yytype_int16;
#endif

#ifndef YYSIZE_T
# ifdef __SIZE_TYPE__
#  define YYSIZE_T __SIZE_TYPE__
# elif defined size_t
#  define YYSIZE_T size_t
# elif ! defined YYSIZE_T && (defined __STDC__ || defined __C99__FUNC__ \
     || defined __cplusplus || defined _MSC_VER)
#  include <stddef.h> /* INFRINGES ON USER NAME SPACE */
#  define YYSIZE_T size_t
# else
#  define YYSIZE_T unsigned int
# endif
#endif

#define YYSIZE_MAXIMUM ((YYSIZE_T) -1)

#ifndef YY_
# if defined YYENABLE_NLS && YYENABLE_NLS
#  if ENABLE_NLS
#   include <libintl.h> /* INFRINGES ON USER NAME SPACE */
#   define YY_(msgid) dgettext ("bison-runtime", msgid)
#  endif
# endif
# ifndef YY_
#  define YY_(msgid) msgid
# endif
#endif

/* Suppress unused-variable warnings by "using" E.  */
#if ! defined lint || defined __GNUC__
# define YYUSE(e) ((void) (e))
#else
# define YYUSE(e) /* empty */
#endif

/* Identity function, used to suppress warnings about constant conditions.  */
#ifndef lint
# define YYID(n) (n)
#else
#if (defined __STDC__ || defined __C99__FUNC__ \
     || defined __cplusplus || defined _MSC_VER)
static int
YYID (int i)
#else
static int
YYID (i)
    int i;
#endif
{
  return i;
}
#endif

#if ! defined yyoverflow || YYERROR_VERBOSE

/* The parser invokes alloca or malloc; define the necessary symbols.  */

# ifdef YYSTACK_USE_ALLOCA
#  if YYSTACK_USE_ALLOCA
#   ifdef __GNUC__
#    define YYSTACK_ALLOC __builtin_alloca
#   elif defined __BUILTIN_VA_ARG_INCR
#    include <alloca.h> /* INFRINGES ON USER NAME SPACE */
#   elif defined _AIX
#    define YYSTACK_ALLOC __alloca
#   elif defined _MSC_VER
#    include <malloc.h> /* INFRINGES ON USER NAME SPACE */
#    define alloca _alloca
#   else
#    define YYSTACK_ALLOC alloca
#    if ! defined _ALLOCA_H && ! defined _STDLIB_H && (defined __STDC__ || defined __C99__FUNC__ \
     || defined __cplusplus || defined _MSC_VER)
#     include <stdlib.h> /* INFRINGES ON USER NAME SPACE */
#     ifndef _STDLIB_H
#      define _STDLIB_H 1
#     endif
#    endif
#   endif
#  endif
# endif

# ifdef YYSTACK_ALLOC
   /* Pacify GCC's `empty if-body' warning.  */
#  define YYSTACK_FREE(Ptr) do { /* empty */; } while (YYID (0))
#  ifndef YYSTACK_ALLOC_MAXIMUM
    /* The OS might guarantee only one guard page at the bottom of the stack,
       and a page size can be as small as 4096 bytes.  So we cannot safely
       invoke alloca (N) if N exceeds 4096.  Use a slightly smaller number
       to allow for a few compiler-allocated temporary stack slots.  */
#   define YYSTACK_ALLOC_MAXIMUM 4032 /* reasonable circa 2006 */
#  endif
# else
#  define YYSTACK_ALLOC YYMALLOC
#  define YYSTACK_FREE YYFREE
#  ifndef YYSTACK_ALLOC_MAXIMUM
#   define YYSTACK_ALLOC_MAXIMUM YYSIZE_MAXIMUM
#  endif
#  if (defined __cplusplus && ! defined _STDLIB_H \
       && ! ((defined YYMALLOC || defined malloc) \
	     && (defined YYFREE || defined free)))
#   include <stdlib.h> /* INFRINGES ON USER NAME SPACE */
#   ifndef _STDLIB_H
#    define _STDLIB_H 1
#   endif
#  endif
#  ifndef YYMALLOC
#   define YYMALLOC malloc
#   if ! defined malloc && ! defined _STDLIB_H && (defined __STDC__ || defined __C99__FUNC__ \
     || defined __cplusplus || defined _MSC_VER)
void *malloc (YYSIZE_T); /* INFRINGES ON USER NAME SPACE */
#   endif
#  endif
#  ifndef YYFREE
#   define YYFREE free
#   if ! defined free && ! defined _STDLIB_H && (defined __STDC__ || defined __C99__FUNC__ \
     || defined __cplusplus || defined _MSC_VER)
void free (void *); /* INFRINGES ON USER NAME SPACE */
#   endif
#  endif
# endif
#endif /* ! defined yyoverflow || YYERROR_VERBOSE */


#if (! defined yyoverflow \
     && (! defined __cplusplus \
	 || (defined YYSTYPE_IS_TRIVIAL && YYSTYPE_IS_TRIVIAL)))

/* A type that is properly aligned for any stack member.  */
union yyalloc
{
  yytype_int16 yyss;
  YYSTYPE yyvs;
  };

/* The size of the maximum gap between one aligned stack and the next.  */
# define YYSTACK_GAP_MAXIMUM (sizeof (union yyalloc) - 1)

/* The size of an array large to enough to hold all stacks, each with
   N elements.  */
# define YYSTACK_BYTES(N) \
     ((N) * (sizeof (yytype_int16) + sizeof (YYSTYPE)) \
      + YYSTACK_GAP_MAXIMUM)

/* Copy COUNT objects from FROM to TO.  The source and destination do
   not overlap.  */
# ifndef YYCOPY
#  if defined __GNUC__ && 1 < __GNUC__
#   define YYCOPY(To, From, Count) \
      __builtin_memcpy (To, From, (Count) * sizeof (*(From)))
#  else
#   define YYCOPY(To, From, Count)		\
      do					\
	{					\
	  YYSIZE_T yyi;				\
	  for (yyi = 0; yyi < (Count); yyi++)	\
	    (To)[yyi] = (From)[yyi];		\
	}					\
      while (YYID (0))
#  endif
# endif

/* Relocate STACK from its old location to the new one.  The
   local variables YYSIZE and YYSTACKSIZE give the old and new number of
   elements in the stack, and YYPTR gives the new location of the
   stack.  Advance YYPTR to a properly aligned location for the next
   stack.  */
# define YYSTACK_RELOCATE(Stack)					\
    do									\
      {									\
	YYSIZE_T yynewbytes;						\
	YYCOPY (&yyptr->Stack, Stack, yysize);				\
	Stack = &yyptr->Stack;						\
	yynewbytes = yystacksize * sizeof (*Stack) + YYSTACK_GAP_MAXIMUM; \
	yyptr += yynewbytes / sizeof (*yyptr);				\
      }									\
    while (YYID (0))

#endif

/* YYFINAL -- State number of the termination state.  */
#define YYFINAL  3
/* YYLAST -- Last index in YYTABLE.  */
#define YYLAST   7616

/* YYNTOKENS -- Number of terminals.  */
#define YYNTOKENS  168
/* YYNNTS -- Number of nonterminals.  */
#define YYNNTS  135
/* YYNRULES -- Number of rules.  */
#define YYNRULES  443
/* YYNRULES -- Number of states.  */
#define YYNSTATES  915

/* YYTRANSLATE(YYLEX) -- Bison symbol number corresponding to YYLEX.  */
#define YYUNDEFTOK  2
#define YYMAXUTOK   396

#define YYTRANSLATE(YYX)						\
  ((unsigned int) (YYX) <= YYMAXUTOK ? yytranslate[YYX] : YYUNDEFTOK)

/* YYTRANSLATE[YYLEX] -- Bison symbol number corresponding to YYLEX.  */
static const yytype_uint8 yytranslate[] =
{
       0,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,    50,     2,     2,   166,    49,    32,     2,
     161,   162,    47,    44,     8,    45,    46,    48,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,    26,   163,
      38,    13,    39,    25,    53,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,    65,     2,   167,    31,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,   164,    30,   165,    52,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     1,     2,     3,     4,
       5,     6,     7,     9,    10,    11,    12,    14,    15,    16,
      17,    18,    19,    20,    21,    22,    23,    24,    27,    28,
      29,    33,    34,    35,    36,    37,    40,    41,    42,    43,
      51,    54,    55,    56,    57,    58,    59,    60,    61,    62,
      63,    64,    66,    67,    68,    69,    70,    71,    72,    73,
      74,    75,    76,    77,    78,    79,    80,    81,    82,    83,
      84,    85,    86,    87,    88,    89,    90,    91,    92,    93,
      94,    95,    96,    97,    98,    99,   100,   101,   102,   103,
     104,   105,   106,   107,   108,   109,   110,   111,   112,   113,
     114,   115,   116,   117,   118,   119,   120,   121,   122,   123,
     124,   125,   126,   127,   128,   129,   130,   131,   132,   133,
     134,   135,   136,   137,   138,   139,   140,   141,   142,   143,
     144,   145,   146,   147,   148,   149,   150,   151,   152,   153,
     154,   155,   156,   157,   158,   159,   160
};

#if YYDEBUG
/* YYPRHS[YYN] -- Index of the first RHS symbol of rule number YYN in
   YYRHS.  */
static const yytype_uint16 yyprhs[] =
{
       0,     0,     3,     5,     8,     9,    11,    15,    17,    19,
      21,    26,    30,    36,    41,    45,    48,    52,    54,    56,
      60,    63,    68,    74,    79,    82,    83,    85,    87,    89,
      94,    96,    99,   101,   103,   105,   109,   117,   128,   134,
     142,   152,   158,   161,   165,   168,   172,   175,   179,   183,
     187,   191,   195,   197,   200,   203,   209,   218,   227,   233,
     235,   242,   248,   252,   256,   259,   261,   270,   271,   273,
     278,   280,   284,   286,   288,   290,   291,   293,   304,   312,
     319,   321,   324,   327,   329,   330,   333,   335,   336,   339,
     340,   343,   345,   349,   350,   353,   355,   358,   360,   365,
     367,   372,   374,   379,   383,   389,   393,   398,   403,   409,
     410,   416,   421,   423,   425,   427,   432,   433,   440,   441,
     449,   450,   453,   454,   458,   460,   461,   464,   468,   474,
     479,   484,   490,   498,   505,   508,   510,   511,   513,   516,
     518,   520,   522,   523,   526,   528,   529,   531,   535,   537,
     540,   543,   547,   549,   551,   554,   559,   563,   569,   571,
     575,   578,   579,   583,   586,   588,   589,   600,   604,   606,
     610,   612,   616,   617,   619,   621,   624,   627,   630,   634,
     636,   640,   642,   644,   648,   653,   657,   658,   660,   662,
     666,   668,   670,   671,   673,   675,   678,   680,   682,   684,
     686,   688,   690,   694,   700,   702,   706,   712,   717,   721,
     723,   724,   726,   730,   732,   739,   743,   748,   755,   758,
     762,   766,   770,   774,   778,   782,   786,   790,   794,   798,
     802,   805,   808,   811,   814,   818,   822,   826,   830,   834,
     838,   842,   846,   850,   854,   858,   862,   866,   870,   874,
     878,   881,   884,   887,   890,   894,   898,   902,   906,   910,
     914,   918,   922,   926,   930,   932,   934,   940,   945,   949,
     951,   954,   957,   960,   963,   966,   969,   972,   975,   978,
     980,   982,   984,   986,   989,   991,  1002,  1014,  1017,  1020,
    1025,  1030,  1032,  1033,  1038,  1042,  1047,  1049,  1052,  1057,
    1064,  1070,  1077,  1084,  1091,  1098,  1103,  1105,  1107,  1111,
    1114,  1116,  1120,  1123,  1125,  1127,  1132,  1134,  1137,  1138,
    1141,  1142,  1145,  1149,  1150,  1154,  1156,  1158,  1160,  1162,
    1164,  1166,  1168,  1170,  1172,  1174,  1176,  1178,  1180,  1182,
    1186,  1189,  1192,  1195,  1200,  1204,  1206,  1210,  1212,  1214,
    1216,  1220,  1223,  1225,  1226,  1229,  1230,  1232,  1238,  1242,
    1246,  1248,  1250,  1252,  1254,  1256,  1258,  1264,  1266,  1269,
    1270,  1274,  1279,  1284,  1288,  1290,  1292,  1293,  1295,  1298,
    1302,  1306,  1308,  1313,  1318,  1320,  1322,  1324,  1326,  1330,
    1333,  1335,  1340,  1345,  1347,  1349,  1354,  1355,  1357,  1359,
    1361,  1366,  1371,  1373,  1375,  1379,  1381,  1384,  1388,  1390,
    1392,  1397,  1398,  1399,  1402,  1408,  1412,  1416,  1418,  1425,
    1430,  1435,  1438,  1443,  1448,  1451,  1454,  1459,  1462,  1465,
    1467,  1471,  1475,  1479,  1484,  1489,  1494,  1499,  1504,  1509,
    1513,  1517,  1526,  1530
};

/* YYRHS -- A `-1'-separated list of the rules' RHS.  */
static const yytype_int16 yyrhs[] =
{
     169,     0,    -1,   170,    -1,   170,   172,    -1,    -1,    75,
      -1,   171,   153,    75,    -1,   178,    -1,   186,    -1,   187,
      -1,   121,   161,   162,   163,    -1,   150,   171,   163,    -1,
     150,   171,   164,   170,   165,    -1,   150,   164,   170,   165,
      -1,   109,   173,   163,    -1,   175,   163,    -1,   173,     8,
     174,    -1,   174,    -1,   171,    -1,   171,    95,    75,    -1,
     153,   171,    -1,   153,   171,    95,    75,    -1,   175,     8,
      75,    13,   263,    -1,   104,    75,    13,   263,    -1,   176,
     177,    -1,    -1,   178,    -1,   186,    -1,   187,    -1,   121,
     161,   162,   163,    -1,   179,    -1,    75,    26,    -1,   137,
      -1,   138,    -1,   140,    -1,   164,   176,   165,    -1,    69,
     161,   269,   162,   178,   207,   209,    -1,    69,   161,   269,
     162,    26,   176,   208,   210,    72,   163,    -1,    87,   161,
     269,   162,   206,    -1,    86,   178,    87,   161,   269,   162,
     163,    -1,    89,   161,   246,   163,   246,   163,   246,   162,
     199,    -1,    96,   161,   269,   162,   203,    -1,   100,   163,
      -1,   100,   269,   163,    -1,   101,   163,    -1,   101,   269,
     163,    -1,   105,   163,    -1,   105,   248,   163,    -1,   105,
     273,   163,    -1,   110,   220,   163,    -1,   116,   222,   163,
      -1,    85,   245,   163,    -1,    79,    -1,   269,   163,    -1,
     249,   163,    -1,   118,   161,   184,   162,   163,    -1,    91,
     161,   273,    95,   198,   197,   162,   200,    -1,    91,   161,
     248,    95,   273,   197,   162,   200,    -1,    93,   161,   202,
     162,   201,    -1,   163,    -1,   106,   164,   176,   165,   180,
     182,    -1,   106,   164,   176,   165,   183,    -1,   108,   269,
     163,    -1,   102,    75,   163,    -1,   180,   181,    -1,   181,
      -1,   107,   161,   255,    77,   162,   164,   176,   165,    -1,
      -1,   183,    -1,   159,   164,   176,   165,    -1,   185,    -1,
     184,     8,   185,    -1,   273,    -1,   189,    -1,   190,    -1,
      -1,    32,    -1,   250,   188,    75,   161,   211,   162,   216,
     164,   176,   165,    -1,   191,    75,   192,   195,   164,   223,
     165,    -1,   193,    75,   194,   164,   223,   165,    -1,   122,
      -1,   115,   122,    -1,   114,   122,    -1,   156,    -1,    -1,
     124,   255,    -1,   123,    -1,    -1,   124,   196,    -1,    -1,
     125,   196,    -1,   255,    -1,   196,     8,   255,    -1,    -1,
     127,   198,    -1,   273,    -1,    32,   273,    -1,   178,    -1,
      26,   176,    90,   163,    -1,   178,    -1,    26,   176,    92,
     163,    -1,   178,    -1,    26,   176,    94,   163,    -1,    75,
      13,   263,    -1,   202,     8,    75,    13,   263,    -1,   164,
     204,   165,    -1,   164,   163,   204,   165,    -1,    26,   204,
      97,   163,    -1,    26,   163,   204,    97,   163,    -1,    -1,
     204,    98,   269,   205,   176,    -1,   204,    99,   205,   176,
      -1,    26,    -1,   163,    -1,   178,    -1,    26,   176,    88,
     163,    -1,    -1,   207,    70,   161,   269,   162,   178,    -1,
      -1,   208,    70,   161,   269,   162,    26,   176,    -1,    -1,
      71,   178,    -1,    -1,    71,    26,   176,    -1,   212,    -1,
      -1,   214,   213,    -1,   214,    32,   213,    -1,   214,    32,
     213,    13,   263,    -1,   214,   213,    13,   263,    -1,   212,
       8,   214,   213,    -1,   212,     8,   214,    32,   213,    -1,
     212,     8,   214,    32,   213,    13,   263,    -1,   212,     8,
     214,   213,    13,   263,    -1,   160,    77,    -1,    77,    -1,
      -1,   215,    -1,    25,   215,    -1,   255,    -1,   129,    -1,
     155,    -1,    -1,    26,   214,    -1,   218,    -1,    -1,   219,
      -1,   218,     8,   219,    -1,   269,    -1,   160,   269,    -1,
      32,   271,    -1,   220,     8,   221,    -1,   221,    -1,    77,
      -1,   166,   270,    -1,   166,   164,   269,   165,    -1,   222,
       8,    77,    -1,   222,     8,    77,    13,   263,    -1,    77,
      -1,    77,    13,   263,    -1,   223,   224,    -1,    -1,   239,
     243,   163,    -1,   244,   163,    -1,   226,    -1,    -1,   240,
     250,   225,   188,    75,   161,   211,   162,   216,   238,    -1,
     109,   227,   228,    -1,   255,    -1,   227,     8,   255,    -1,
     163,    -1,   164,   229,   165,    -1,    -1,   230,    -1,   231,
      -1,   230,   231,    -1,   232,   163,    -1,   236,   163,    -1,
     235,   154,   233,    -1,   255,    -1,   233,     8,   255,    -1,
      75,    -1,   235,    -1,   255,   147,    75,    -1,   234,    95,
     237,    75,    -1,   234,    95,   242,    -1,    -1,   242,    -1,
     163,    -1,   164,   176,   165,    -1,   241,    -1,   117,    -1,
      -1,   241,    -1,   242,    -1,   241,   242,    -1,   111,    -1,
     112,    -1,   113,    -1,   116,    -1,   115,    -1,   114,    -1,
     243,     8,    77,    -1,   243,     8,    77,    13,   263,    -1,
      77,    -1,    77,    13,   263,    -1,   244,     8,    75,    13,
     263,    -1,   104,    75,    13,   263,    -1,   245,     8,   269,
      -1,   269,    -1,    -1,   247,    -1,   247,     8,   269,    -1,
     269,    -1,   128,   161,   292,   162,    13,   269,    -1,   273,
      13,   269,    -1,   273,    13,    32,   273,    -1,   273,    13,
      32,    67,   256,   261,    -1,    66,   269,    -1,   273,    24,
     269,    -1,   273,    23,   269,    -1,   273,    22,   269,    -1,
     273,    21,   269,    -1,   273,    20,   269,    -1,   273,    19,
     269,    -1,   273,    18,   269,    -1,   273,    17,   269,    -1,
     273,    16,   269,    -1,   273,    15,   269,    -1,   273,    14,
     269,    -1,   272,    64,    -1,    64,   272,    -1,   272,    63,
      -1,    63,   272,    -1,   269,    28,   269,    -1,   269,    29,
     269,    -1,   269,     9,   269,    -1,   269,    11,   269,    -1,
     269,    10,   269,    -1,   269,    30,   269,    -1,   269,    32,
     269,    -1,   269,    31,   269,    -1,   269,    46,   269,    -1,
     269,    44,   269,    -1,   269,    45,   269,    -1,   269,    47,
     269,    -1,   269,    48,   269,    -1,   269,    49,   269,    -1,
     269,    43,   269,    -1,   269,    42,   269,    -1,    44,   269,
      -1,    45,   269,    -1,    50,   269,    -1,    52,   269,    -1,
     269,    35,   269,    -1,   269,    34,   269,    -1,   269,    37,
     269,    -1,   269,    36,   269,    -1,   269,    38,   269,    -1,
     269,    41,   269,    -1,   269,    39,   269,    -1,   269,    40,
     269,    -1,   269,    33,   269,    -1,   269,    51,   256,    -1,
     298,    -1,   301,    -1,   269,    25,   269,    26,   269,    -1,
     269,    25,    26,   269,    -1,   269,    27,   269,    -1,   296,
      -1,    62,   269,    -1,    61,   269,    -1,    60,   269,    -1,
      57,   269,    -1,    56,   269,    -1,    55,   269,    -1,    54,
     269,    -1,    68,   260,    -1,    53,   269,    -1,    84,    -1,
     265,    -1,   299,    -1,   300,    -1,    12,   269,    -1,   158,
      -1,   250,   188,   161,   211,   162,   251,   216,   164,   176,
     165,    -1,   116,   250,   188,   161,   211,   162,   251,   216,
     164,   176,   165,    -1,   158,   248,    -1,   158,   273,    -1,
     158,   269,   127,   248,    -1,   158,   269,   127,   273,    -1,
     103,    -1,    -1,   109,   161,   252,   162,    -1,   252,     8,
      77,    -1,   252,     8,    32,    77,    -1,    77,    -1,    32,
      77,    -1,   171,   161,   217,   162,    -1,   150,   153,   171,
     161,   217,   162,    -1,   153,   171,   161,   217,   162,    -1,
     254,   147,    75,   161,   217,   162,    -1,   281,   147,    75,
     161,   217,   162,    -1,   281,   147,   279,   161,   217,   162,
      -1,   254,   147,   279,   161,   217,   162,    -1,   279,   161,
     217,   162,    -1,   116,    -1,   171,    -1,   150,   153,   171,
      -1,   153,   171,    -1,   171,    -1,   150,   153,   171,    -1,
     153,   171,    -1,   254,    -1,   257,    -1,   284,   126,   288,
     258,    -1,   284,    -1,   258,   259,    -1,    -1,   126,   288,
      -1,    -1,   161,   162,    -1,   161,   269,   162,    -1,    -1,
     161,   217,   162,    -1,    73,    -1,    74,    -1,    83,    -1,
     133,    -1,   134,    -1,   152,    -1,   130,    -1,   131,    -1,
     157,    -1,   132,    -1,   151,    -1,   144,    -1,   262,    -1,
     171,    -1,   150,   153,   171,    -1,   153,   171,    -1,    44,
     263,    -1,    45,   263,    -1,   129,   161,   266,   162,    -1,
      65,   266,   167,    -1,   264,    -1,   254,   147,    75,    -1,
      76,    -1,   302,    -1,   171,    -1,   150,   153,   171,    -1,
     153,   171,    -1,   262,    -1,    -1,   268,   267,    -1,    -1,
       8,    -1,   268,     8,   263,   127,   263,    -1,   268,     8,
     263,    -1,   263,   127,   263,    -1,   263,    -1,   270,    -1,
     248,    -1,   273,    -1,   273,    -1,   273,    -1,   283,   126,
     288,   278,   274,    -1,   283,    -1,   274,   275,    -1,    -1,
     126,   288,   278,    -1,   276,    65,   287,   167,    -1,   277,
      65,   287,   167,    -1,   161,   217,   162,    -1,   277,    -1,
     276,    -1,    -1,   285,    -1,   291,   285,    -1,   254,   147,
     279,    -1,   281,   147,   279,    -1,   285,    -1,   282,    65,
     287,   167,    -1,   253,    65,   287,   167,    -1,   284,    -1,
     282,    -1,   253,    -1,   285,    -1,   161,   301,   162,    -1,
     291,   285,    -1,   280,    -1,   285,    65,   287,   167,    -1,
     285,   164,   269,   165,    -1,   286,    -1,    77,    -1,   166,
     164,   269,   165,    -1,    -1,   269,    -1,   289,    -1,   279,
      -1,   289,    65,   287,   167,    -1,   289,   164,   269,   165,
      -1,   290,    -1,    75,    -1,   164,   269,   165,    -1,   166,
      -1,   291,   166,    -1,   292,     8,   293,    -1,   293,    -1,
     273,    -1,   128,   161,   292,   162,    -1,    -1,    -1,   295,
     267,    -1,   295,     8,   269,   127,   269,    -1,   295,     8,
     269,    -1,   269,   127,   269,    -1,   269,    -1,   295,     8,
     269,   127,    32,   271,    -1,   295,     8,    32,   271,    -1,
     269,   127,    32,   271,    -1,    32,   271,    -1,   119,   161,
     297,   162,    -1,   120,   161,   273,   162,    -1,     7,   269,
      -1,     6,   269,    -1,     5,   161,   269,   162,    -1,     4,
     269,    -1,     3,   269,    -1,   273,    -1,   297,     8,   273,
      -1,   161,   269,   162,    -1,   161,   249,   162,    -1,   300,
      65,   287,   167,    -1,   299,    65,   287,   167,    -1,    83,
      65,   287,   167,    -1,   302,    65,   287,   167,    -1,    75,
      65,   287,   167,    -1,   129,   161,   294,   162,    -1,    65,
     294,   167,    -1,    67,   256,   261,    -1,    67,   122,   261,
     192,   195,   164,   223,   165,    -1,   254,   147,    75,    -1,
     281,   147,    75,    -1
};

/* YYRLINE[YYN] -- source line where rule number YYN was defined.  */
static const yytype_uint16 yyrline[] =
{
       0,   207,   207,   213,   216,   222,   225,   231,   232,   233,
     234,   239,   246,   252,   260,   265,   272,   275,   282,   287,
     293,   299,   309,   316,   326,   329,   335,   336,   337,   338,
     346,   347,   353,   356,   359,   365,   368,   398,   417,   424,
     432,   445,   452,   459,   466,   473,   480,   487,   494,   501,
     506,   511,   516,   520,   524,   528,   534,   552,   569,   575,
     579,   588,   597,   605,   616,   620,   626,   638,   641,   645,
     654,   658,   665,   669,   673,   677,   680,   686,   702,   714,
     729,   733,   740,   747,   754,   757,   763,   767,   770,   778,
     781,   789,   792,   798,   801,   807,   808,   816,   817,   825,
     826,   834,   835,   843,   849,   860,   863,   873,   878,   890,
     893,   901,   911,   912,   916,   917,   925,   928,   938,   941,
     951,   954,   962,   965,   973,   974,   980,   988,   997,  1006,
    1014,  1022,  1031,  1041,  1053,  1057,  1063,  1066,  1067,  1074,
    1077,  1080,  1086,  1089,  1096,  1097,  1103,  1106,  1112,  1113,
    1117,  1124,  1128,  1135,  1138,  1142,  1149,  1157,  1165,  1173,
    1184,  1187,  1193,  1201,  1205,  1208,  1208,  1226,  1234,  1237,
    1243,  1246,  1252,  1255,  1261,  1265,  1272,  1275,  1281,  1289,
    1293,  1300,  1304,  1310,  1318,  1324,  1333,  1336,  1344,  1347,
    1353,  1354,  1361,  1364,  1371,  1375,  1381,  1382,  1383,  1384,
    1385,  1386,  1390,  1397,  1404,  1411,  1421,  1430,  1442,  1445,
    1452,  1455,  1460,  1463,  1470,  1478,  1484,  1494,  1508,  1513,
    1519,  1525,  1531,  1537,  1543,  1549,  1555,  1561,  1567,  1573,
    1579,  1584,  1589,  1594,  1599,  1605,  1611,  1617,  1623,  1629,
    1635,  1641,  1647,  1673,  1679,  1685,  1691,  1697,  1703,  1709,
    1715,  1720,  1725,  1730,  1735,  1741,  1747,  1753,  1759,  1765,
    1771,  1777,  1783,  1789,  1795,  1796,  1797,  1805,  1813,  1819,
    1820,  1825,  1830,  1835,  1840,  1845,  1850,  1855,  1860,  1865,
    1869,  1870,  1871,  1872,  1877,  1883,  1898,  1922,  1928,  1934,
    1940,  1949,  1953,  1956,  1964,  1967,  1972,  1976,  1985,  1990,
    1997,  2003,  2012,  2021,  2030,  2039,  2047,  2050,  2053,  2057,
    2064,  2067,  2071,  2078,  2079,  2083,  2098,  2102,  2105,  2111,
    2117,  2120,  2124,  2132,  2135,  2141,  2144,  2147,  2150,  2153,
    2156,  2159,  2162,  2165,  2168,  2171,  2174,  2180,  2181,  2182,
    2186,  2190,  2195,  2200,  2205,  2210,  2214,  2222,  2223,  2224,
    2225,  2228,  2231,  2235,  2238,  2244,  2247,  2251,  2262,  2269,
    2276,  2286,  2287,  2291,  2295,  2299,  2303,  2329,  2333,  2336,
    2342,  2352,  2358,  2367,  2373,  2374,  2375,  2381,  2382,  2397,
    2402,  2410,  2414,  2420,  2429,  2430,  2431,  2435,  2436,  2439,
    2451,  2455,  2461,  2467,  2471,  2474,  2482,  2485,  2491,  2492,
    2496,  2502,  2508,  2512,  2516,  2522,  2525,  2540,  2543,  2550,
    2551,  2555,  2561,  2564,  2570,  2577,  2584,  2591,  2598,  2605,
    2612,  2619,  2629,  2639,  2649,  2652,  2655,  2665,  2668,  2674,
    2678,  2684,  2689,  2695,  2701,  2707,  2713,  2719,  2728,  2733,
    2741,  2747,  2765,  2770
};
#endif

#if YYDEBUG || YYERROR_VERBOSE || YYTOKEN_TABLE
/* YYTNAME[SYMBOL-NUM] -- String name of the symbol SYMBOL-NUM.
   First, the terminals, then, starting at YYNTOKENS, nonterminals.  */
static const char *const yytname[] =
{
  "$end", "error", "$undefined", "T_REQUIRE_ONCE", "T_REQUIRE", "T_EVAL",
  "T_INCLUDE_ONCE", "T_INCLUDE", "','", "T_LOGICAL_OR", "T_LOGICAL_XOR",
  "T_LOGICAL_AND", "T_PRINT", "'='", "T_SR_EQUAL", "T_SL_EQUAL",
  "T_XOR_EQUAL", "T_OR_EQUAL", "T_AND_EQUAL", "T_MOD_EQUAL",
  "T_CONCAT_EQUAL", "T_DIV_EQUAL", "T_MUL_EQUAL", "T_MINUS_EQUAL",
  "T_PLUS_EQUAL", "'?'", "':'", "T_COALESCE", "T_BOOLEAN_OR",
  "T_BOOLEAN_AND", "'|'", "'^'", "'&'", "T_SPACESHIP",
  "T_IS_NOT_IDENTICAL", "T_IS_IDENTICAL", "T_IS_NOT_EQUAL", "T_IS_EQUAL",
  "'<'", "'>'", "T_IS_GREATER_OR_EQUAL", "T_IS_SMALLER_OR_EQUAL", "T_SR",
  "T_SL", "'+'", "'-'", "'.'", "'*'", "'/'", "'%'", "'!'", "T_INSTANCEOF",
  "'~'", "'@'", "T_UNSET_CAST", "T_BOOL_CAST", "T_OBJECT_CAST",
  "T_ARRAY_CAST", "T_BINARY_CAST", "T_UNICODE_CAST", "T_STRING_CAST",
  "T_DOUBLE_CAST", "T_INT_CAST", "T_DEC", "T_INC", "'['", "T_CLONE",
  "T_NEW", "T_EXIT", "T_IF", "T_ELSEIF", "T_ELSE", "T_ENDIF", "T_LNUMBER",
  "T_DNUMBER", "T_STRING", "T_STRING_VARNAME", "T_VARIABLE",
  "T_NUM_STRING", "T_INLINE_HTML", "T_CHARACTER", "T_BAD_CHARACTER",
  "T_ENCAPSED_AND_WHITESPACE", "T_CONSTANT_ENCAPSED_STRING",
  "T_BACKTICKS_EXPR", "T_ECHO", "T_DO", "T_WHILE", "T_ENDWHILE", "T_FOR",
  "T_ENDFOR", "T_FOREACH", "T_ENDFOREACH", "T_DECLARE", "T_ENDDECLARE",
  "T_AS", "T_SWITCH", "T_ENDSWITCH", "T_CASE", "T_DEFAULT", "T_BREAK",
  "T_CONTINUE", "T_GOTO", "T_FUNCTION", "T_CONST", "T_RETURN", "T_TRY",
  "T_CATCH", "T_THROW", "T_USE", "T_GLOBAL", "T_PUBLIC", "T_PROTECTED",
  "T_PRIVATE", "T_FINAL", "T_ABSTRACT", "T_STATIC", "T_VAR", "T_UNSET",
  "T_ISSET", "T_EMPTY", "T_HALT_COMPILER", "T_CLASS", "T_INTERFACE",
  "T_EXTENDS", "T_IMPLEMENTS", "T_OBJECT_OPERATOR", "T_DOUBLE_ARROW",
  "T_LIST", "T_ARRAY", "T_CLASS_C", "T_METHOD_C", "T_FUNC_C", "T_LINE",
  "T_FILE", "T_COMMENT", "T_DOC_COMMENT", "T_OPEN_TAG",
  "T_OPEN_TAG_WITH_ECHO", "T_OPEN_TAG_FAKE", "T_CLOSE_TAG", "T_WHITESPACE",
  "T_START_HEREDOC", "T_END_HEREDOC", "T_HEREDOC",
  "T_DOLLAR_OPEN_CURLY_BRACES", "T_CURLY_OPEN", "T_PAAMAYIM_NEKUDOTAYIM",
  "T_BINARY_DOUBLE", "T_BINARY_HEREDOC", "T_NAMESPACE", "T_NS_C", "T_DIR",
  "T_NS_SEPARATOR", "T_INSTEADOF", "T_CALLABLE", "T_TRAIT", "T_TRAIT_C",
  "T_YIELD", "T_FINALLY", "T_ELLIPSIS", "'('", "')'", "';'", "'{'", "'}'",
  "'$'", "']'", "$accept", "start", "top_statement_list", "namespace_name",
  "top_statement", "use_declarations", "use_declaration",
  "constant_declaration", "inner_statement_list", "inner_statement",
  "statement", "unticked_statement", "catch_list", "catch",
  "finally_statement", "non_empty_finally_statement", "unset_variables",
  "unset_variable", "function_declaration_statement",
  "class_declaration_statement", "is_reference",
  "unticked_function_declaration_statement",
  "unticked_class_declaration_statement", "class_entry_type",
  "extends_from", "interface_entry", "interface_extends_list",
  "implements_list", "interface_list", "foreach_optional_arg",
  "foreach_variable", "for_statement", "foreach_statement",
  "declare_statement", "declare_list", "switch_case_list", "case_list",
  "case_separator", "while_statement", "elseif_list", "new_elseif_list",
  "else_single", "new_else_single", "parameter_list",
  "non_empty_parameter_list", "parameter", "optional_type", "type",
  "return_type", "function_call_parameter_list",
  "non_empty_function_call_parameter_list", "argument", "global_var_list",
  "global_var", "static_var_list", "class_statement_list",
  "class_statement", "@1", "trait_use_statement", "trait_list",
  "trait_adaptations", "trait_adaptation_list",
  "non_empty_trait_adaptation_list", "trait_adaptation_statement",
  "trait_precedence", "trait_reference_list", "trait_method_reference",
  "trait_method_reference_fully_qualified", "trait_alias",
  "trait_modifiers", "method_body", "variable_modifiers",
  "method_modifiers", "non_empty_member_modifiers", "member_modifier",
  "class_variable_declaration", "class_constant_declaration",
  "echo_expr_list", "for_expr", "non_empty_for_expr",
  "expr_without_variable", "yield_expr", "function", "lexical_vars",
  "lexical_var_list", "function_call", "class_name",
  "fully_qualified_class_name", "class_name_reference",
  "dynamic_class_name_reference", "dynamic_class_name_variable_properties",
  "dynamic_class_name_variable_property", "exit_expr", "ctor_arguments",
  "common_scalar", "static_scalar", "static_class_constant", "scalar",
  "static_array_pair_list", "possible_comma",
  "non_empty_static_array_pair_list", "expr", "r_variable", "w_variable",
  "rw_variable", "variable", "variable_properties", "variable_property",
  "array_method_dereference", "method", "method_or_not",
  "variable_without_objects", "static_member", "variable_class_name",
  "array_function_dereference", "base_variable_with_function_calls",
  "base_variable", "reference_variable", "compound_variable", "dim_offset",
  "object_property", "object_dim_list", "variable_name",
  "simple_indirect_reference", "assignment_list",
  "assignment_list_element", "array_pair_list",
  "non_empty_array_pair_list", "internal_functions_in_yacc",
  "isset_variables", "parenthesis_expr", "combined_scalar_offset",
  "combined_scalar", "new_expr", "class_constant", 0
};
#endif

# ifdef YYPRINT
/* YYTOKNUM[YYLEX-NUM] -- Internal token number corresponding to
   token YYLEX-NUM.  */
static const yytype_uint16 yytoknum[] =
{
       0,   256,   257,   258,   259,   260,   261,   262,    44,   263,
     264,   265,   266,    61,   267,   268,   269,   270,   271,   272,
     273,   274,   275,   276,   277,    63,    58,   278,   279,   280,
     124,    94,    38,   281,   282,   283,   284,   285,    60,    62,
     286,   287,   288,   289,    43,    45,    46,    42,    47,    37,
      33,   290,   126,    64,   291,   292,   293,   294,   295,   296,
     297,   298,   299,   300,   301,    91,   302,   303,   304,   305,
     306,   307,   308,   309,   310,   311,   312,   313,   314,   315,
     316,   317,   318,   319,   320,   321,   322,   323,   324,   325,
     326,   327,   328,   329,   330,   331,   332,   333,   334,   335,
     336,   337,   338,   339,   340,   341,   342,   343,   344,   345,
     346,   347,   348,   349,   350,   351,   352,   353,   354,   355,
     356,   357,   358,   359,   360,   361,   362,   363,   364,   365,
     366,   367,   368,   369,   370,   371,   372,   373,   374,   375,
     376,   377,   378,   379,   380,   381,   382,   383,   384,   385,
     386,   387,   388,   389,   390,   391,   392,   393,   394,   395,
     396,    40,    41,    59,   123,   125,    36,    93
};
# endif

/* YYR1[YYN] -- Symbol number of symbol that rule YYN derives.  */
static const yytype_uint16 yyr1[] =
{
       0,   168,   169,   170,   170,   171,   171,   172,   172,   172,
     172,   172,   172,   172,   172,   172,   173,   173,   174,   174,
     174,   174,   175,   175,   176,   176,   177,   177,   177,   177,
     178,   178,   178,   178,   178,   179,   179,   179,   179,   179,
     179,   179,   179,   179,   179,   179,   179,   179,   179,   179,
     179,   179,   179,   179,   179,   179,   179,   179,   179,   179,
     179,   179,   179,   179,   180,   180,   181,   182,   182,   183,
     184,   184,   185,   186,   187,   188,   188,   189,   190,   190,
     191,   191,   191,   191,   192,   192,   193,   194,   194,   195,
     195,   196,   196,   197,   197,   198,   198,   199,   199,   200,
     200,   201,   201,   202,   202,   203,   203,   203,   203,   204,
     204,   204,   205,   205,   206,   206,   207,   207,   208,   208,
     209,   209,   210,   210,   211,   211,   212,   212,   212,   212,
     212,   212,   212,   212,   213,   213,   214,   214,   214,   215,
     215,   215,   216,   216,   217,   217,   218,   218,   219,   219,
     219,   220,   220,   221,   221,   221,   222,   222,   222,   222,
     223,   223,   224,   224,   224,   225,   224,   226,   227,   227,
     228,   228,   229,   229,   230,   230,   231,   231,   232,   233,
     233,   234,   234,   235,   236,   236,   237,   237,   238,   238,
     239,   239,   240,   240,   241,   241,   242,   242,   242,   242,
     242,   242,   243,   243,   243,   243,   244,   244,   245,   245,
     246,   246,   247,   247,   248,   248,   248,   248,   248,   248,
     248,   248,   248,   248,   248,   248,   248,   248,   248,   248,
     248,   248,   248,   248,   248,   248,   248,   248,   248,   248,
     248,   248,   248,   248,   248,   248,   248,   248,   248,   248,
     248,   248,   248,   248,   248,   248,   248,   248,   248,   248,
     248,   248,   248,   248,   248,   248,   248,   248,   248,   248,
     248,   248,   248,   248,   248,   248,   248,   248,   248,   248,
     248,   248,   248,   248,   248,   248,   248,   249,   249,   249,
     249,   250,   251,   251,   252,   252,   252,   252,   253,   253,
     253,   253,   253,   253,   253,   253,   254,   254,   254,   254,
     255,   255,   255,   256,   256,   257,   257,   258,   258,   259,
     260,   260,   260,   261,   261,   262,   262,   262,   262,   262,
     262,   262,   262,   262,   262,   262,   262,   263,   263,   263,
     263,   263,   263,   263,   263,   263,   264,   265,   265,   265,
     265,   265,   265,   266,   266,   267,   267,   268,   268,   268,
     268,   269,   269,   270,   271,   272,   273,   273,   274,   274,
     275,   276,   276,   277,   278,   278,   278,   279,   279,   280,
     280,   281,   282,   282,   283,   283,   283,   284,   284,   284,
     284,   285,   285,   285,   286,   286,   287,   287,   288,   288,
     289,   289,   289,   290,   290,   291,   291,   292,   292,   293,
     293,   293,   294,   294,   295,   295,   295,   295,   295,   295,
     295,   295,   296,   296,   296,   296,   296,   296,   296,   297,
     297,   298,   298,   299,   299,   299,   299,   299,   300,   300,
     301,   301,   302,   302
};

/* YYR2[YYN] -- Number of symbols composing right hand side of rule YYN.  */
static const yytype_uint8 yyr2[] =
{
       0,     2,     1,     2,     0,     1,     3,     1,     1,     1,
       4,     3,     5,     4,     3,     2,     3,     1,     1,     3,
       2,     4,     5,     4,     2,     0,     1,     1,     1,     4,
       1,     2,     1,     1,     1,     3,     7,    10,     5,     7,
       9,     5,     2,     3,     2,     3,     2,     3,     3,     3,
       3,     3,     1,     2,     2,     5,     8,     8,     5,     1,
       6,     5,     3,     3,     2,     1,     8,     0,     1,     4,
       1,     3,     1,     1,     1,     0,     1,    10,     7,     6,
       1,     2,     2,     1,     0,     2,     1,     0,     2,     0,
       2,     1,     3,     0,     2,     1,     2,     1,     4,     1,
       4,     1,     4,     3,     5,     3,     4,     4,     5,     0,
       5,     4,     1,     1,     1,     4,     0,     6,     0,     7,
       0,     2,     0,     3,     1,     0,     2,     3,     5,     4,
       4,     5,     7,     6,     2,     1,     0,     1,     2,     1,
       1,     1,     0,     2,     1,     0,     1,     3,     1,     2,
       2,     3,     1,     1,     2,     4,     3,     5,     1,     3,
       2,     0,     3,     2,     1,     0,    10,     3,     1,     3,
       1,     3,     0,     1,     1,     2,     2,     2,     3,     1,
       3,     1,     1,     3,     4,     3,     0,     1,     1,     3,
       1,     1,     0,     1,     1,     2,     1,     1,     1,     1,
       1,     1,     3,     5,     1,     3,     5,     4,     3,     1,
       0,     1,     3,     1,     6,     3,     4,     6,     2,     3,
       3,     3,     3,     3,     3,     3,     3,     3,     3,     3,
       2,     2,     2,     2,     3,     3,     3,     3,     3,     3,
       3,     3,     3,     3,     3,     3,     3,     3,     3,     3,
       2,     2,     2,     2,     3,     3,     3,     3,     3,     3,
       3,     3,     3,     3,     1,     1,     5,     4,     3,     1,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     1,
       1,     1,     1,     2,     1,    10,    11,     2,     2,     4,
       4,     1,     0,     4,     3,     4,     1,     2,     4,     6,
       5,     6,     6,     6,     6,     4,     1,     1,     3,     2,
       1,     3,     2,     1,     1,     4,     1,     2,     0,     2,
       0,     2,     3,     0,     3,     1,     1,     1,     1,     1,
       1,     1,     1,     1,     1,     1,     1,     1,     1,     3,
       2,     2,     2,     4,     3,     1,     3,     1,     1,     1,
       3,     2,     1,     0,     2,     0,     1,     5,     3,     3,
       1,     1,     1,     1,     1,     1,     5,     1,     2,     0,
       3,     4,     4,     3,     1,     1,     0,     1,     2,     3,
       3,     1,     4,     4,     1,     1,     1,     1,     3,     2,
       1,     4,     4,     1,     1,     4,     0,     1,     1,     1,
       4,     4,     1,     1,     3,     1,     2,     3,     1,     1,
       4,     0,     0,     2,     5,     3,     3,     1,     6,     4,
       4,     2,     4,     4,     2,     2,     4,     2,     2,     1,
       3,     3,     3,     4,     4,     4,     4,     4,     4,     3,
       3,     8,     3,     3
};

/* YYDEFACT[STATE-NAME] -- Default rule to reduce with in state
   STATE-NUM when YYTABLE doesn't specify something else to do.  Zero
   means the default is an error.  */
static const yytype_uint16 yydefact[] =
{
       4,     0,     2,     1,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,   412,     0,     0,   320,     0,   325,
     326,     5,   347,   394,    52,   327,   279,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,   291,     0,     0,
       0,     0,     0,     0,     0,     0,   306,     0,     0,     0,
       0,    80,    86,     0,     0,   331,   332,   334,   328,   329,
      32,    33,    34,   336,     0,   335,   330,     0,    83,   333,
     284,     0,    59,    25,   405,   349,     3,     0,     7,    30,
       8,     9,    73,    74,     0,     0,   362,     0,    75,   386,
       0,   352,   280,     0,   361,     0,   363,     0,   390,     0,
     385,   367,   384,   387,   393,     0,   269,   264,   281,   282,
     265,   348,     5,   306,     0,   284,    75,   428,   427,     0,
     425,   424,   283,   250,   251,   252,   253,   278,   276,   275,
     274,   273,   272,   271,   270,     5,   306,     0,     0,     0,
     307,     0,   233,   365,     0,   231,     0,   417,     0,   355,
     218,   323,     0,     0,   307,   313,   323,   314,     0,   316,
     387,     0,     0,   277,     0,    31,   396,   396,     0,   209,
       0,     0,   210,     0,     0,     0,    42,     0,    44,     0,
       0,     0,    46,   362,     0,   363,    25,     0,     0,    18,
       0,    17,   153,     0,     0,   152,    82,    81,   158,     0,
      75,     0,     0,     0,     0,   411,   412,     0,     4,     0,
     351,   362,     0,   363,     0,     0,   265,     0,     0,     0,
     145,     0,    15,    84,    87,    54,    76,     0,   396,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,    53,   232,
     230,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,   145,     0,   396,     0,   396,     0,   406,
     389,   396,   396,   396,     0,     0,     0,   309,     0,     0,
       0,   421,   364,     0,   439,   356,   413,   145,    84,     0,
     309,     0,   440,     0,     0,   389,   321,     0,     0,   397,
       0,     0,     0,    51,     0,     0,     0,   211,   213,   362,
     363,     0,     0,     0,    43,    45,    63,     0,    47,    48,
       0,    62,    20,     0,     0,    14,     0,   154,   363,     0,
      49,     0,     0,    50,     0,     0,    70,    72,   429,     0,
       0,     0,     0,   409,     0,   408,     0,   350,     0,    11,
       4,   145,     0,   432,   431,   388,     0,    35,    24,    26,
      27,    28,     0,     6,     0,     0,     0,   144,   146,   148,
       0,     0,    89,     0,     0,     0,   136,     0,   442,   379,
     377,     0,   236,   238,   237,     0,     0,   268,   234,   235,
     239,   241,   240,   262,   255,   254,   257,   256,   258,   260,
     261,   259,   249,   248,   243,   244,   242,   245,   246,   247,
     263,     0,   215,   229,   228,   227,   226,   225,   224,   223,
     222,   221,   220,   219,     0,   443,   380,     0,   403,     0,
     399,   376,   398,   402,     0,     0,     0,     0,     0,   426,
     308,     0,     0,     0,   416,     0,   415,     0,    89,   308,
     379,   380,   318,   322,     0,   437,   435,   208,     0,     0,
     210,     0,     0,     0,     0,     0,     0,     0,     0,     0,
     353,   327,     0,     0,     0,   338,     0,   337,    23,   345,
       0,     0,    19,    16,     0,   151,   159,   156,   136,     0,
       0,     0,   422,   423,    10,   411,   411,     0,   438,   145,
      13,     0,     0,   362,   363,     0,   395,   150,   149,   298,
       0,     0,     0,     0,   310,    85,     0,     0,    88,    91,
     161,   136,     0,   140,   141,     0,   124,     0,   137,   139,
     383,   145,   145,   378,   267,     0,     0,   216,   305,   145,
     145,   382,     0,   145,   375,   374,   369,   396,     0,   391,
     392,   434,   433,   436,   420,   419,     0,   324,     0,   315,
      25,   116,     0,    25,   114,    38,     0,   212,    93,     0,
      93,    95,   103,     0,    25,   101,    58,   109,   109,    41,
     341,   342,   360,     0,   355,   353,     0,   340,     0,     0,
       0,    67,    65,    61,    21,   155,     0,     0,    71,    55,
     430,     0,   407,     0,     0,    12,   300,     0,   147,    22,
       0,   312,    90,   161,     0,   192,     0,   138,   292,   136,
       0,   135,     0,   126,     0,     0,   266,   323,     0,     0,
     404,     0,   396,   396,   366,     0,     0,     0,   414,   161,
       0,   317,   118,   120,     0,     0,   210,     0,     0,    96,
       0,     0,     0,   109,     0,   109,     0,     0,   344,   356,
     354,     0,   339,   346,     0,    25,    64,    60,    68,   157,
     292,   410,   214,   299,    29,   311,   192,    92,     0,     0,
     196,   197,   198,   201,   200,   199,   191,    79,   160,   164,
       0,     0,   190,   194,     0,   142,     0,   142,     0,   127,
     134,     0,   301,   304,   217,   302,   303,   373,     0,     0,
       0,   368,   400,   401,   418,   192,   319,   122,     0,     0,
      36,    39,     0,     0,    94,     0,     0,   104,     0,     0,
       0,     0,     0,     0,   105,   359,   358,   343,     0,     0,
     142,    78,     0,     0,   168,   204,     0,   165,   195,     0,
     163,   136,     0,     0,     0,     0,   130,     0,   129,   371,
     372,   376,   441,     0,     0,     0,     0,   121,   115,     0,
      25,    99,    57,    56,   102,     0,   107,     0,   112,   113,
      25,   106,     0,     0,    69,     0,     0,     0,   170,   172,
     167,     0,     0,   162,    75,     0,   143,    25,     0,   296,
       0,    25,   131,     0,   128,   370,     0,    25,     0,     0,
      25,    97,    40,     0,   108,    25,   111,   357,     0,    25,
     207,   169,     5,     0,   173,   174,     0,     0,   182,     0,
       0,   205,   202,     0,     0,     0,   297,     0,   293,     0,
       0,   133,     0,   123,    37,     0,     0,     0,   110,    25,
       0,   171,   175,   176,   186,     0,   177,     0,     0,     0,
     206,    77,     0,   294,   285,   132,     0,   117,     0,   100,
       0,   286,     0,   185,   178,   179,   183,   203,   136,   295,
      25,    98,    66,   184,     0,     0,   119,   180,   142,     0,
     188,    25,   166,     0,   189
};

/* YYDEFGOTO[NTERM-NUM].  */
static const yytype_int16 yydefgoto[] =
{
      -1,     1,     2,    85,    86,   200,   201,    87,   227,   378,
     379,    89,   611,   612,   687,   613,   355,   356,   380,   381,
     237,    92,    93,    94,   392,    95,   394,   537,   538,   668,
     590,   832,   792,   596,   332,   599,   674,   800,   585,   663,
     737,   740,   785,   545,   546,   643,   547,   548,   772,   386,
     387,   388,   204,   205,   209,   635,   708,   814,   709,   763,
     810,   843,   844,   845,   846,   894,   847,   848,   849,   892,
     912,   710,   711,   712,   713,   766,   714,   178,   326,   327,
      96,    97,   126,   717,   820,    99,   100,   549,   166,   167,
     579,   661,   173,   308,   101,   602,   499,   102,   603,   306,
     604,   103,   104,   301,   105,   106,   654,   731,   564,   565,
     566,   107,   108,   109,   110,   111,   112,   113,   114,   320,
     451,   452,   453,   115,   364,   365,   158,   159,   116,   359,
     117,   118,   119,   120,   121
};

/* YYPACT[STATE-NUM] -- Index in YYTABLE of the portion describing
   STATE-NUM.  */
#define YYPACT_NINF -696
static const yytype_int16 yypact[] =
{
    -696,    58,  1746,  -696,  6049,  6049,   -73,  6049,  6049,  6049,
    6049,  6049,  6049,  6049,  6049,  6049,  6049,  6049,  6049,  6049,
    6049,  6049,   395,   395,  4719,  6049,   264,   -71,   -58,  -696,
    -696,    34,  -696,  -696,  -696,    71,  -696,  6049,  4444,   -23,
      55,    84,    86,    89,  4852,  4985,   188,  -696,   205,  5118,
     145,  6049,    -7,   -47,   102,   190,    -3,   160,   162,   164,
     169,  -696,  -696,   182,   184,  -696,  -696,  -696,  -696,  -696,
    -696,  -696,  -696,  -696,   129,  -696,  -696,   272,  -696,  -696,
    6049,  6182,  -696,  -696,   198,   113,  -696,    16,  -696,  -696,
    -696,  -696,  -696,  -696,   279,   290,  -696,   204,   345,   319,
     252,  -696,  -696,  6554,  -696,   242,  1385,   243,  -696,   281,
     361,   309,  -696,   -17,  -696,   -34,  -696,  -696,   374,   376,
    -696,   377,   379,   342,   293,  -696,   345,  7393,  7393,  6049,
    7393,  7393,  1595,  -696,  -696,   396,  -696,  -696,  -696,  -696,
    -696,  -696,  -696,  -696,  -696,  -696,  -696,   298,   272,   385,
     -84,   306,  -696,  -696,   316,  -696,   395,  7150,   289,   457,
    -696,   303,   323,   272,   324,   333,   303,  -696,   335,   355,
     -12,   -34,  5251,  -696,  6049,  -696,  6049,  6049,    21,  7393,
     405,  6049,  6049,  6049,   420,  6049,  -696,  6605,  -696,  6648,
     352,   484,  -696,   354,  7393,  1458,  -696,  6691,   272,   -19,
      23,  -696,  -696,   167,    26,  -696,  -696,  -696,   485,    27,
     345,   395,   395,   395,   343,   338,  4719,   272,  -696,    85,
     149,   172,  7193,    93,   347,  6742,   360,  1888,  6049,   445,
    4586,   449,  -696,   404,   406,  -696,  -696,    -8,  6049,    12,
    6049,  6049,  6049,  5384,  6049,  6049,  6049,  6049,  6049,  6049,
    6049,  6049,  6049,  6049,  6049,  6049,  6049,  6049,  6049,  6049,
    6049,  6049,  6049,  6049,  6049,  6049,  6049,   467,  -696,  -696,
    -696,  5517,  6049,  6049,  6049,  6049,  6049,  6049,  6049,  6049,
    6049,  6049,  6049,  4586,    62,  6049,    -5,  6049,  6049,   198,
      57,  6049,  6049,  6049,   368,  6785,   272,   -80,   360,    74,
     176,  -696,  -696,  5650,  -696,  5783,  -696,  4586,   404,   272,
     324,    99,  -696,    99,    -5,   -33,  -696,  6828,  6878,  7393,
     367,   370,  6049,  -696,   386,  6921,   375,   538,  7393,   462,
    1287,   545,    25,  6964,  -696,  -696,  -696,  7262,  -696,  -696,
    2030,  -696,   106,   487,    -7,  -696,  6049,  -696,  -696,   -47,
    -696,  7262,   482,  -696,   402,    33,  -696,  -696,  -696,    41,
     413,   401,   416,  -696,    43,  -696,   414,   203,  1440,  -696,
    -696,  4586,  6049,  -696,  -696,  -696,   417,  -696,  -696,  -696,
    -696,  -696,  1225,  -696,   395,  6049,   419,   558,  -696,  7393,
     566,   135,   459,   135,   418,   424,    79,   421,   425,   426,
     -33,   -34,  7435,  7474,  1595,  6049,  7321,  7499,  7522,  7544,
    7565,  4639,  1738,  1880,  1880,  1880,  1880,  1880,   776,   776,
     776,   776,   548,   548,   358,   358,   358,   396,   396,   396,
    -696,   202,  1595,  1595,  1595,  1595,  1595,  1595,  1595,  1595,
    1595,  1595,  1595,  1595,   427,   437,   439,   435,  -696,  6049,
    -696,   442,    -9,  -696,   440,  6327,   443,   444,   447,  -696,
     115,   425,   437,   395,  7393,   395,  7254,   450,   459,   324,
    -696,  -696,  -696,  -696,  3734,  -696,  -696,  7393,  6049,  3876,
    6049,  6049,   395,   187,  7262,   529,  4018,    35,  7262,  7262,
    7262,  -696,   448,   452,   272,   -55,   475,  -696,  -696,  -696,
     -63,   531,  -696,  -696,  6370,  -696,  -696,   612,    79,   395,
     460,   395,  -696,  -696,  -696,   338,   338,   613,  -696,  4586,
    -696,  1604,   465,   195,   151,   468,  -696,  -696,  7393,  -696,
    4586,  7262,   478,   272,   324,  -696,   135,   470,   624,  -696,
    -696,    79,   274,  -696,  -696,   474,   629,    51,  -696,  -696,
    -696,  4586,  4586,   -33,  7499,  6049,   467,  -696,  -696,  4586,
    4586,  -696,  6413,  4586,   573,   576,  -696,  6049,  6049,  -696,
    -696,  -696,  -696,  -696,  -696,  -696,  5916,  -696,   479,   518,
    -696,  -696,  7014,  -696,  -696,  -696,   483,  7393,   522,   395,
     522,  -696,  -696,   637,  -696,  -696,  -696,   488,   491,  -696,
    -696,  -696,   528,   492,   650,  7262,   272,   137,   586,   504,
     503,   -63,  -696,  -696,  -696,  -696,  7262,   506,  -696,  -696,
    -696,    44,  -696,  6049,   510,  -696,  -696,   517,  -696,  -696,
     272,   324,   624,  -696,   135,   278,   520,  -696,   574,   117,
      63,  -696,   608,   673,   526,   530,  7499,   303,   532,   534,
    -696,   535,  6049,  6049,   564,   533,  6466,   395,  7393,  -696,
      -5,  -696,  3592,   326,   536,  2172,  6049,   187,   540,  -696,
     542,  7262,  2314,  -696,   321,  -696,   132,  7262,  -696,  7262,
    -696,   543,   232,  -696,   135,  -696,  -696,  -696,  -696,  -696,
     574,  -696,  1595,  -696,  -696,   324,   456,  -696,   618,   135,
    -696,  -696,  -696,  -696,  -696,  -696,  -696,  -696,  -696,  -696,
     621,   342,   346,  -696,    28,   681,   547,   681,    52,   699,
    -696,  7262,  -696,  -696,  -696,  -696,  -696,  -696,   546,   549,
      -5,  -696,  -696,  -696,  -696,   562,  -696,   340,   554,  4444,
    -696,  -696,   556,   559,  -696,  4160,  4160,  -696,   560,   334,
     561,  6049,    31,   159,  -696,  -696,   595,  -696,   648,  2456,
     681,  -696,   713,    18,  -696,   715,    30,  -696,  -696,   655,
    -696,   117,   569,    46,   570,    63,   722,  7262,  -696,  -696,
    -696,   442,  -696,   575,   711,   666,  6049,  -696,  -696,  4302,
    -696,  -696,  -696,  -696,  -696,   577,  -696,  6511,  -696,  -696,
    -696,  -696,  7262,   579,  -696,   578,  7262,   135,  -696,   142,
    -696,  7262,   662,  -696,   345,   731,  -696,  -696,   669,  -696,
      47,  -696,   734,  7262,  -696,  -696,  6049,  -696,   585,  7057,
    -696,  -696,  -696,  2598,  -696,  -696,  3592,  -696,   587,  -696,
    -696,  -696,   657,   588,   142,  -696,   591,   660,   605,   597,
     614,  -696,   750,   689,  7262,  2740,  -696,   201,  -696,  2882,
    7262,  -696,  7100,  3592,  -696,  4444,  3024,   604,  3592,  -696,
    3166,  -696,  -696,  -696,   438,   135,  -696,   708,  7262,   623,
    -696,  -696,   718,  -696,  -696,  -696,   762,  -696,   634,  -696,
    3308,  -696,   724,   725,   794,  -696,  -696,  -696,    79,  -696,
    -696,  -696,  -696,  -696,   135,   641,  3592,  -696,   681,   259,
    -696,  -696,  -696,  3450,  -696
};

/* YYPGOTO[NTERM-NUM].  */
static const yytype_int16 yypgoto[] =
{
    -696,  -696,  -193,   -15,  -696,  -696,   463,  -696,  -182,  -696,
       4,  -696,  -696,   193,  -696,   200,  -696,   300,     2,    15,
    -125,  -696,  -696,  -696,   505,  -696,  -696,   364,   292,   246,
     166,  -696,    91,  -696,  -696,  -696,  -453,    49,  -696,  -696,
    -696,  -696,  -696,  -496,  -696,  -613,  -611,   296,  -695,  -244,
    -696,   310,  -696,   494,  -696,  -558,  -696,  -696,  -696,  -696,
    -696,  -696,  -696,     5,  -696,  -696,  -696,  -696,  -696,  -696,
    -696,  -696,  -696,  -696,  -689,  -696,  -696,  -696,  -459,  -696,
     -40,   767,    -2,   161,  -696,  -696,    24,  -373,  -252,  -696,
    -696,  -696,  -696,  -163,   572,   610,  -696,  -696,   245,   248,
    -696,   761,   651,  -368,   415,   709,  -696,  -696,  -696,  -696,
      75,  -220,  -696,   688,  -696,  -696,   -24,   -13,  -696,  -167,
    -309,  -696,  -696,    60,   344,   339,   642,  -696,  -696,  -696,
    -696,  -696,  -696,     1,  -696
};

/* YYTABLE[YYPACT[STATE-NUM]].  What to do in state STATE-NUM.  If
   positive, shift that token.  If negative, reduce the rule which
   number is the opposite.  If zero, do what YYDEFACT says.
   If YYTABLE_NINF, syntax error.  */
#define YYTABLE_NINF -382
static const yytype_int16 yytable[] =
{
      98,   294,   169,   312,    90,   472,    88,   150,   150,   193,
     321,   164,   617,   170,   340,   430,   527,    91,   535,   399,
     539,   586,   774,   768,   231,   368,   807,   719,   718,   322,
     202,   344,   287,   485,   349,   352,   769,   199,   812,   444,
     221,   509,   180,    33,   609,   636,   151,   151,   287,   511,
     165,   516,   516,   287,   210,   857,   567,   798,     3,   219,
     175,   597,   220,   467,   446,   805,   450,   395,   145,   229,
     448,   397,    33,   229,   208,   696,   343,   230,   818,   399,
     446,   371,   226,   640,   775,   354,   171,   398,   129,    33,
     172,   470,  -307,   471,   450,   574,   610,   575,   229,   176,
      47,   735,   290,   174,   542,   776,   271,   272,   273,   274,
     275,   276,   277,   278,   279,   280,   281,   282,   447,   203,
     454,   210,   287,   819,   456,   457,   458,   522,   641,   641,
    -381,   288,   289,   297,   229,  -381,   177,   445,   181,    33,
     641,   150,   542,   329,  -377,   676,   198,   288,   310,   461,
     298,    33,   288,   396,   145,   568,  -365,  -365,   315,   449,
     816,    84,   822,   539,   271,   272,   273,   274,   275,   276,
     277,   278,   279,   280,   281,   282,    33,   521,    84,   232,
     151,   808,   809,   342,   323,   893,   345,   486,   150,   350,
     353,   770,   145,   813,   799,   510,   150,   150,   150,   598,
     150,   501,   367,   512,   145,   517,   691,   743,   543,   858,
     145,   642,   642,   909,  -365,  -365,   182,   842,  -378,   589,
     749,   288,   753,   642,   206,    98,   400,   151,    84,   532,
     751,   752,   533,   882,   544,   151,   151,   151,   229,   151,
      84,  -125,   145,   169,    33,   183,   543,   184,   369,   370,
     185,   462,   164,    33,   170,  -288,  -288,   751,   752,   229,
    -307,   697,   145,   190,    33,    84,   229,   532,   229,   556,
     533,   400,   544,   400,   230,   624,   519,   145,   883,    33,
     191,   460,   217,   146,  -309,   532,   400,   400,   533,   734,
     229,   165,   532,   218,   469,   533,  -309,   754,   400,   401,
     400,   400,   229,   146,   647,   269,   270,   644,   645,   196,
     371,   758,   207,  -290,  -290,   648,   649,   147,   146,   651,
     148,   211,   495,   212,   801,   213,   764,   171,   149,   199,
     214,   346,   523,    84,  -287,  -287,   495,   147,    98,   145,
     148,    33,    84,   215,   401,   216,   401,   145,   149,   145,
    -308,   736,   147,    84,   233,   148,   229,  -289,  -289,   401,
     401,   496,   228,   149,   519,   234,    98,   235,    84,   150,
      90,   401,    88,   401,   401,   496,   534,   236,   534,  -308,
     146,   534,   698,    91,   238,   229,   161,   699,   553,   700,
     701,   702,   703,   704,   705,   706,   738,   739,   662,   239,
     655,   665,   905,   543,   283,   264,   265,   266,   151,   267,
     783,   784,   672,   145,   162,    33,   150,   163,   750,   751,
     752,   781,   910,   911,   532,   149,   285,   533,   284,   544,
      84,   795,   751,   752,   841,   286,   850,   152,   155,   291,
     450,   292,   293,   707,   176,    47,   217,   267,   150,  -193,
     150,   296,    26,   299,   146,   151,   304,   700,   701,   702,
     703,   704,   705,   300,   307,   305,   362,   150,   150,   495,
     145,   850,    33,   495,   495,   495,   309,   229,   581,   607,
     311,   314,   313,   584,   724,   728,   729,   151,   147,   151,
     595,   148,   324,   534,   150,   331,   150,   337,   351,   149,
     150,   150,   895,   759,    84,   361,   151,   151,   496,   373,
     450,   146,   496,   496,   496,   336,   495,   338,   631,    98,
     383,   534,   375,    90,   390,    88,   534,   534,   391,   396,
     393,   907,   169,   151,   475,   151,    91,   476,   480,   151,
     151,   164,   145,   170,    33,   147,   481,   478,   148,   700,
     701,   702,   703,   704,   705,   496,   149,   482,   484,   507,
     698,    84,   502,   508,   514,   699,   530,   700,   701,   702,
     703,   704,   705,   706,   150,   513,   518,   515,   525,   531,
     165,   529,   540,   146,   536,   541,   551,   552,   550,   558,
     495,   682,   261,   262,   263,   264,   265,   266,   559,   267,
     560,   495,   561,   563,   593,   606,   614,   569,   833,   605,
     571,   572,   577,   151,   573,   695,   171,   162,   836,   534,
     163,   761,   608,   619,   534,   616,   623,   626,   149,   496,
     627,   630,   634,    84,   633,   855,   638,   639,   652,   859,
     496,   653,   150,   659,   660,   863,   666,   400,   866,   667,
     671,   673,   150,   868,   675,   677,   495,   870,   679,   678,
      98,   683,   495,    98,   495,   684,   698,   685,   690,   534,
      98,   699,   693,   700,   701,   702,   703,   704,   705,   706,
     694,   151,   715,   716,   534,   720,   721,   890,   722,   853,
     730,   151,   723,   762,   725,   496,   726,   727,   765,   741,
     732,   496,   745,   496,   746,   757,   495,   771,   773,   767,
     154,   154,   777,   779,   168,   786,   780,   400,   906,   788,
     401,   789,   802,   794,   796,   803,   806,   782,   811,   913,
     815,   153,   153,   817,   821,   823,   826,   827,   828,   852,
     834,   838,   839,   787,   854,   496,   856,   860,   864,   791,
     791,   869,  -181,   871,   873,   874,   534,    98,   195,   875,
     876,   877,   495,   878,   879,   127,   128,   889,   130,   131,
     132,   133,   134,   135,   136,   137,   138,   139,   140,   141,
     142,   143,   144,   896,   898,   157,   160,   495,   900,   223,
     401,   495,   534,   831,   534,   899,   495,   901,   179,   903,
    -187,   496,   904,   908,   686,   187,   189,   503,   495,   618,
     194,   688,   197,   468,  -382,  -382,  -382,  -382,   259,   260,
     261,   262,   263,   264,   265,   266,   496,   267,   632,   534,
     496,    98,   578,   744,    98,   496,   670,   793,   637,   495,
     628,   222,   225,   505,   154,   495,   835,   496,   224,   872,
     681,   760,   680,    98,   347,   622,   825,    98,   366,   621,
     534,    98,     0,   495,    98,   302,    98,     0,    98,   887,
       0,     0,     0,     0,     0,     0,     0,     0,   496,     0,
       0,     0,     0,   534,   496,     0,     0,     0,    98,   534,
     295,   154,   330,     0,     0,     0,     0,     0,     0,   154,
     154,   154,   496,   154,    98,     0,     0,     0,     0,   497,
       0,    98,   348,     0,     0,     0,     0,     0,     0,     0,
     357,   358,   360,   497,   363,     0,     0,     0,     0,     0,
       0,     0,     0,   317,     0,   318,     0,   319,   319,     0,
       0,     0,   325,   328,   194,     0,   333,   498,     0,     0,
       0,     0,     0,     0,     0,   168,     0,     0,     0,     0,
       0,   506,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,   157,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,   382,
       0,   389,     0,     0,     0,     0,     0,     0,     0,   319,
       0,   402,   403,   404,   406,   407,   408,   409,   410,   411,
     412,   413,   414,   415,   416,   417,   418,   419,   420,   421,
     422,   423,   424,   425,   426,   427,   428,   429,     0,     0,
       0,     0,   432,   433,   434,   435,   436,   437,   438,   439,
     440,   441,   442,   443,   389,     0,   319,     0,   319,   455,
       0,     0,   319,   319,   319,     0,   497,     0,     0,     0,
     497,   497,   497,     0,   464,     0,   466,     0,   389,     0,
       0,     0,   154,     0,     0,     0,     0,     0,     0,     0,
       0,   524,     0,   477,     0,     0,     0,     0,     0,     0,
       0,     0,     0,   302,   592,     0,     0,     0,   600,   601,
       0,     0,     0,   497,     0,     0,     0,   504,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,   154,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,   389,   194,     0,     0,     0,     0,     0,     0,
     557,   629,     0,     0,     0,     0,   528,     0,     0,     0,
       0,   154,     0,   154,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,   554,     0,     0,     0,
     154,   154,   302,     0,   302,     0,     0,   497,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,   497,     0,
       0,   588,   591,     0,     0,     0,     0,   154,     0,   154,
       0,     0,     0,   154,   154,     0,     0,     0,     0,     0,
     562,     0,     0,     0,     0,     0,     0,     0,   357,     0,
     620,     0,     0,     0,   363,   363,   689,     0,     0,     0,
       0,     0,     0,     0,   240,   241,   242,     0,     0,   582,
       0,   328,   587,   497,   168,     0,     0,     0,     0,   497,
     243,   497,   244,   245,   246,   247,   248,   249,   250,   251,
     252,   253,   254,   255,   256,   257,   258,   259,   260,   261,
     262,   263,   264,   265,   266,     0,   267,   154,     0,     0,
     389,   747,     0,     0,     0,     0,     0,   755,     0,   756,
       0,   389,     0,   497,     0,     0,     0,     0,   669,     0,
     271,   272,   273,   274,   275,   276,   277,   278,   279,   280,
     281,   282,   389,   389,     0,     0,   646,     0,     0,     0,
     389,   389,     0,     0,   389,     0,     0,     0,   319,   656,
       0,   778,     0,     0,     0,     0,     0,   658,     0,     0,
       0,     0,     0,     0,     0,   154,     0,     0,     0,   497,
    -365,  -365,     0,     0,     0,   154,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,   302,     0,     0,     0,
       0,     0,     0,     0,   497,     0,   591,     0,   497,     0,
       0,     0,   483,   497,   692,     0,     0,   824,     0,     0,
     526,     0,     0,     0,     0,   497,     0,     0,   271,   272,
     273,   274,   275,   276,   277,   278,   279,   280,   281,   282,
       0,     0,   837,   319,   319,     0,   840,     0,     0,     0,
       0,   851,     0,     0,     0,     0,   497,   328,     0,     0,
       0,     0,   497,   861,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     4,     5,     6,     7,     8,  -365,  -365,
     497,     0,     9,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,   880,     0,     0,     0,     0,     0,
     885,   271,   272,   273,   274,   275,   276,   277,   278,   279,
     280,   281,   282,     0,    10,    11,     0,     0,   897,     0,
      12,     0,    13,    14,    15,    16,    17,    18,     0,     0,
      19,    20,    21,    22,    23,    24,    25,    26,    27,    28,
       0,     0,   797,    29,    30,    31,    32,    33,     0,    34,
       0,  -365,  -365,    35,    36,    37,    38,    39,     0,    40,
       0,    41,     0,    42,     0,     0,    43,     0,     0,     0,
      44,    45,    46,    47,    48,    49,    50,   829,    51,    52,
      53,     0,     0,     0,    54,    55,    56,     0,    57,    58,
      59,    60,    61,    62,     0,     0,     0,     0,    63,    64,
      65,    66,    67,    68,    69,     0,     0,    70,    71,     0,
      72,     0,     0,     0,    73,     0,     0,   862,     0,     0,
      74,    75,    76,    77,     0,     0,    78,    79,    80,     0,
       0,    81,     0,    82,    83,   520,    84,     4,     5,     6,
       7,     8,     0,     0,     0,     0,     9,     0,     0,     0,
     243,   339,   244,   245,   246,   247,   248,   249,   250,   251,
     252,   253,   254,   255,   256,   257,   258,   259,   260,   261,
     262,   263,   264,   265,   266,     0,   267,     0,    10,    11,
       0,     0,     0,     0,    12,     0,    13,    14,    15,    16,
      17,    18,     0,     0,    19,    20,    21,    22,    23,    24,
      25,    26,    27,    28,     0,     0,     0,    29,    30,    31,
      32,    33,     0,    34,     0,     0,     0,    35,    36,    37,
      38,    39,     0,    40,     0,    41,     0,    42,     0,     0,
      43,     0,     0,     0,    44,    45,    46,    47,    48,    49,
      50,     0,    51,    52,    53,     0,     0,     0,    54,    55,
      56,     0,    57,    58,    59,    60,    61,    62,     0,     0,
       0,     0,    63,    64,    65,    66,    67,    68,    69,     0,
       0,    70,    71,     0,    72,     0,     0,     0,    73,     4,
       5,     6,     7,     8,    74,    75,    76,    77,     9,     0,
      78,    79,    80,     0,     0,    81,     0,    82,    83,   625,
      84,   250,   251,   252,   253,   254,   255,   256,   257,   258,
     259,   260,   261,   262,   263,   264,   265,   266,     0,   267,
      10,    11,     0,     0,     0,     0,    12,     0,    13,    14,
      15,    16,    17,    18,     0,     0,    19,    20,    21,    22,
      23,    24,    25,    26,    27,    28,     0,     0,     0,    29,
      30,    31,    32,    33,     0,    34,     0,     0,     0,    35,
      36,    37,    38,    39,     0,    40,     0,    41,     0,    42,
       0,     0,    43,     0,     0,     0,    44,    45,    46,    47,
      48,    49,    50,     0,    51,    52,    53,     0,     0,     0,
      54,    55,    56,     0,    57,    58,    59,    60,    61,    62,
       0,     0,     0,     0,    63,    64,    65,    66,    67,    68,
      69,     0,     0,    70,    71,     0,    72,     0,     0,     0,
      73,     4,     5,     6,     7,     8,    74,    75,    76,    77,
       9,     0,    78,    79,    80,     0,     0,    81,     0,    82,
      83,     0,    84,  -382,  -382,  -382,  -382,  -382,   255,   256,
     257,   258,   259,   260,   261,   262,   263,   264,   265,   266,
       0,   267,    10,    11,     0,     0,     0,     0,    12,     0,
      13,    14,    15,    16,    17,    18,     0,     0,    19,    20,
      21,    22,    23,    24,    25,    26,    27,    28,     0,     0,
       0,    29,    30,    31,    32,    33,     0,    34,     0,     0,
       0,    35,    36,    37,    38,    39,     0,    40,     0,    41,
       0,    42,     0,     0,    43,     0,     0,     0,    44,    45,
      46,    47,     0,    49,    50,     0,    51,     0,    53,     0,
       0,     0,    54,    55,    56,     0,    57,    58,    59,   376,
      61,    62,     0,     0,     0,     0,    63,    64,    65,    66,
      67,    68,    69,     0,     0,    70,    71,     0,    72,     0,
       0,     0,    73,     4,     5,     6,     7,     8,   124,    75,
      76,    77,     9,     0,    78,    79,    80,     0,     0,    81,
       0,    82,    83,   377,    84,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,    10,    11,     0,     0,     0,     0,
      12,     0,    13,    14,    15,    16,    17,    18,     0,     0,
      19,    20,    21,    22,    23,    24,    25,    26,    27,    28,
       0,     0,     0,    29,    30,    31,    32,    33,     0,    34,
       0,     0,     0,    35,    36,    37,    38,    39,     0,    40,
       0,    41,     0,    42,     0,     0,    43,     0,     0,     0,
      44,    45,    46,    47,     0,    49,    50,     0,    51,     0,
      53,     0,     0,     0,    54,    55,    56,     0,    57,    58,
      59,   376,    61,    62,     0,     0,     0,     0,    63,    64,
      65,    66,    67,    68,    69,     0,     0,    70,    71,     0,
      72,     0,     0,     0,    73,     4,     5,     6,     7,     8,
     124,    75,    76,    77,     9,     0,    78,    79,    80,     0,
       0,    81,     0,    82,    83,   500,    84,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,    10,    11,     0,     0,
       0,     0,    12,     0,    13,    14,    15,    16,    17,    18,
       0,     0,    19,    20,    21,    22,    23,    24,    25,    26,
      27,    28,     0,     0,     0,    29,    30,    31,    32,    33,
       0,    34,     0,     0,     0,    35,    36,    37,    38,    39,
     742,    40,     0,    41,     0,    42,     0,     0,    43,     0,
       0,     0,    44,    45,    46,    47,     0,    49,    50,     0,
      51,     0,    53,     0,     0,     0,    54,    55,    56,     0,
      57,    58,    59,   376,    61,    62,     0,     0,     0,     0,
      63,    64,    65,    66,    67,    68,    69,     0,     0,    70,
      71,     0,    72,     0,     0,     0,    73,     4,     5,     6,
       7,     8,   124,    75,    76,    77,     9,     0,    78,    79,
      80,     0,     0,    81,     0,    82,    83,     0,    84,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,    10,    11,
       0,     0,     0,     0,    12,     0,    13,    14,    15,    16,
      17,    18,     0,     0,    19,    20,    21,    22,    23,    24,
      25,    26,    27,    28,     0,     0,     0,    29,    30,    31,
      32,    33,     0,    34,     0,     0,     0,    35,    36,    37,
      38,    39,     0,    40,     0,    41,     0,    42,   748,     0,
      43,     0,     0,     0,    44,    45,    46,    47,     0,    49,
      50,     0,    51,     0,    53,     0,     0,     0,    54,    55,
      56,     0,    57,    58,    59,   376,    61,    62,     0,     0,
       0,     0,    63,    64,    65,    66,    67,    68,    69,     0,
       0,    70,    71,     0,    72,     0,     0,     0,    73,     4,
       5,     6,     7,     8,   124,    75,    76,    77,     9,     0,
      78,    79,    80,     0,     0,    81,     0,    82,    83,     0,
      84,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
      10,    11,     0,     0,     0,     0,    12,     0,    13,    14,
      15,    16,    17,    18,     0,     0,    19,    20,    21,    22,
      23,    24,    25,    26,    27,    28,     0,     0,     0,    29,
      30,    31,    32,    33,     0,    34,     0,     0,     0,    35,
      36,    37,    38,    39,     0,    40,     0,    41,     0,    42,
       0,     0,    43,     0,     0,     0,    44,    45,    46,    47,
       0,    49,    50,     0,    51,     0,    53,     0,     0,     0,
      54,    55,    56,     0,    57,    58,    59,   376,    61,    62,
       0,     0,     0,     0,    63,    64,    65,    66,    67,    68,
      69,     0,     0,    70,    71,     0,    72,     0,     0,     0,
      73,     4,     5,     6,     7,     8,   124,    75,    76,    77,
       9,     0,    78,    79,    80,     0,     0,    81,     0,    82,
      83,   804,    84,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,    10,    11,     0,     0,     0,     0,    12,     0,
      13,    14,    15,    16,    17,    18,     0,     0,    19,    20,
      21,    22,    23,    24,    25,    26,    27,    28,     0,     0,
       0,    29,    30,    31,    32,    33,     0,    34,     0,     0,
       0,    35,    36,    37,    38,    39,     0,    40,     0,    41,
     867,    42,     0,     0,    43,     0,     0,     0,    44,    45,
      46,    47,     0,    49,    50,     0,    51,     0,    53,     0,
       0,     0,    54,    55,    56,     0,    57,    58,    59,   376,
      61,    62,     0,     0,     0,     0,    63,    64,    65,    66,
      67,    68,    69,     0,     0,    70,    71,     0,    72,     0,
       0,     0,    73,     4,     5,     6,     7,     8,   124,    75,
      76,    77,     9,     0,    78,    79,    80,     0,     0,    81,
       0,    82,    83,     0,    84,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,    10,    11,     0,     0,     0,     0,
      12,     0,    13,    14,    15,    16,    17,    18,     0,     0,
      19,    20,    21,    22,    23,    24,    25,    26,    27,    28,
       0,     0,     0,    29,    30,    31,    32,    33,     0,    34,
       0,     0,     0,    35,    36,    37,    38,    39,     0,    40,
       0,    41,     0,    42,     0,     0,    43,     0,     0,     0,
      44,    45,    46,    47,     0,    49,    50,     0,    51,     0,
      53,     0,     0,     0,    54,    55,    56,     0,    57,    58,
      59,   376,    61,    62,     0,     0,     0,     0,    63,    64,
      65,    66,    67,    68,    69,     0,     0,    70,    71,     0,
      72,     0,     0,     0,    73,     4,     5,     6,     7,     8,
     124,    75,    76,    77,     9,     0,    78,    79,    80,     0,
       0,    81,     0,    82,    83,   881,    84,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,    10,    11,     0,     0,
       0,     0,    12,     0,    13,    14,    15,    16,    17,    18,
       0,     0,    19,    20,    21,    22,    23,    24,    25,    26,
      27,    28,     0,     0,     0,    29,    30,    31,    32,    33,
       0,    34,     0,     0,     0,    35,    36,    37,    38,    39,
       0,    40,     0,    41,     0,    42,     0,     0,    43,     0,
       0,     0,    44,    45,    46,    47,     0,    49,    50,     0,
      51,     0,    53,     0,     0,     0,    54,    55,    56,     0,
      57,    58,    59,   376,    61,    62,     0,     0,     0,     0,
      63,    64,    65,    66,    67,    68,    69,     0,     0,    70,
      71,     0,    72,     0,     0,     0,    73,     4,     5,     6,
       7,     8,   124,    75,    76,    77,     9,     0,    78,    79,
      80,     0,     0,    81,     0,    82,    83,   884,    84,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,    10,    11,
       0,     0,     0,     0,    12,     0,    13,    14,    15,    16,
      17,    18,     0,     0,    19,    20,    21,    22,    23,    24,
      25,    26,    27,    28,     0,     0,     0,    29,    30,    31,
      32,    33,     0,    34,     0,     0,     0,    35,    36,    37,
      38,    39,     0,    40,   888,    41,     0,    42,     0,     0,
      43,     0,     0,     0,    44,    45,    46,    47,     0,    49,
      50,     0,    51,     0,    53,     0,     0,     0,    54,    55,
      56,     0,    57,    58,    59,   376,    61,    62,     0,     0,
       0,     0,    63,    64,    65,    66,    67,    68,    69,     0,
       0,    70,    71,     0,    72,     0,     0,     0,    73,     4,
       5,     6,     7,     8,   124,    75,    76,    77,     9,     0,
      78,    79,    80,     0,     0,    81,     0,    82,    83,     0,
      84,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
      10,    11,     0,     0,     0,     0,    12,     0,    13,    14,
      15,    16,    17,    18,     0,     0,    19,    20,    21,    22,
      23,    24,    25,    26,    27,    28,     0,     0,     0,    29,
      30,    31,    32,    33,     0,    34,     0,     0,     0,    35,
      36,    37,    38,    39,     0,    40,     0,    41,     0,    42,
       0,     0,    43,     0,     0,     0,    44,    45,    46,    47,
       0,    49,    50,     0,    51,     0,    53,     0,     0,     0,
      54,    55,    56,     0,    57,    58,    59,   376,    61,    62,
       0,     0,     0,     0,    63,    64,    65,    66,    67,    68,
      69,     0,     0,    70,    71,     0,    72,     0,     0,     0,
      73,     4,     5,     6,     7,     8,   124,    75,    76,    77,
       9,     0,    78,    79,    80,     0,     0,    81,     0,    82,
      83,   891,    84,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,    10,    11,     0,     0,     0,     0,    12,     0,
      13,    14,    15,    16,    17,    18,     0,     0,    19,    20,
      21,    22,    23,    24,    25,    26,    27,    28,     0,     0,
       0,    29,    30,    31,    32,    33,     0,    34,     0,     0,
       0,    35,    36,    37,    38,    39,     0,    40,     0,    41,
       0,    42,     0,     0,    43,     0,     0,     0,    44,    45,
      46,    47,     0,    49,    50,     0,    51,     0,    53,     0,
       0,     0,    54,    55,    56,     0,    57,    58,    59,   376,
      61,    62,     0,     0,     0,     0,    63,    64,    65,    66,
      67,    68,    69,     0,     0,    70,    71,     0,    72,     0,
       0,     0,    73,     4,     5,     6,     7,     8,   124,    75,
      76,    77,     9,     0,    78,    79,    80,     0,     0,    81,
       0,    82,    83,   902,    84,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,    10,    11,     0,     0,     0,     0,
      12,     0,    13,    14,    15,    16,    17,    18,     0,     0,
      19,    20,    21,    22,    23,    24,    25,    26,    27,    28,
       0,     0,     0,    29,    30,    31,    32,    33,     0,    34,
       0,     0,     0,    35,    36,    37,    38,    39,     0,    40,
       0,    41,     0,    42,     0,     0,    43,     0,     0,     0,
      44,    45,    46,    47,     0,    49,    50,     0,    51,     0,
      53,     0,     0,     0,    54,    55,    56,     0,    57,    58,
      59,   376,    61,    62,     0,     0,     0,     0,    63,    64,
      65,    66,    67,    68,    69,     0,     0,    70,    71,     0,
      72,     0,     0,     0,    73,     4,     5,     6,     7,     8,
     124,    75,    76,    77,     9,     0,    78,    79,    80,     0,
       0,    81,     0,    82,    83,   914,    84,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,    10,    11,     0,     0,
       0,     0,    12,     0,    13,    14,    15,    16,    17,    18,
       0,     0,    19,    20,    21,    22,    23,    24,    25,    26,
      27,    28,     0,     0,     0,    29,    30,    31,    32,    33,
       0,    34,     0,     0,     0,    35,    36,    37,    38,    39,
       0,    40,     0,    41,     0,    42,     0,     0,    43,     0,
       0,     0,    44,    45,    46,    47,     0,    49,    50,     0,
      51,     0,    53,     0,     0,     0,    54,    55,    56,     0,
      57,    58,    59,   376,    61,    62,     0,     0,     0,     0,
      63,    64,    65,    66,    67,    68,    69,     0,     0,    70,
      71,     0,    72,     0,     0,     0,    73,     4,     5,     6,
       7,     8,   124,    75,    76,    77,     9,     0,    78,    79,
      80,     0,     0,    81,     0,    82,    83,     0,    84,     0,
     580,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,    10,    11,
       0,     0,     0,     0,    12,     0,    13,    14,    15,    16,
      17,    18,     0,     0,    19,    20,    21,    22,    23,    24,
      25,    26,    27,    28,     0,     0,     0,    29,    30,    31,
      32,    33,     0,    34,     0,     0,     0,    35,    36,    37,
      38,    39,     0,    40,     0,    41,     0,    42,     0,     0,
      43,     0,     0,     0,    44,    45,    46,    47,     0,    49,
      50,     0,    51,     0,    53,     0,     0,     0,     0,     0,
      56,     0,    57,    58,    59,     0,     0,     0,     0,     0,
       0,     0,    63,    64,    65,    66,    67,    68,    69,     0,
       0,    70,    71,     0,    72,     0,     0,     0,    73,     4,
       5,     6,     7,     8,   124,    75,    76,    77,     9,     0,
       0,    79,    80,     0,     0,    81,     0,    82,    83,     0,
      84,     0,   583,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
      10,    11,     0,     0,     0,     0,    12,     0,    13,    14,
      15,    16,    17,    18,     0,     0,    19,    20,    21,    22,
      23,    24,    25,    26,    27,    28,     0,     0,     0,    29,
      30,    31,    32,    33,     0,    34,     0,     0,     0,    35,
      36,    37,    38,    39,     0,    40,     0,    41,     0,    42,
       0,     0,    43,     0,     0,     0,    44,    45,    46,    47,
       0,    49,    50,     0,    51,     0,    53,     0,     0,     0,
       0,     0,    56,     0,    57,    58,    59,     0,     0,     0,
       0,     0,     0,     0,    63,    64,    65,    66,    67,    68,
      69,     0,     0,    70,    71,     0,    72,     0,     0,     0,
      73,     4,     5,     6,     7,     8,   124,    75,    76,    77,
       9,     0,     0,    79,    80,     0,     0,    81,     0,    82,
      83,     0,    84,     0,   594,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,    10,    11,     0,     0,     0,     0,    12,     0,
      13,    14,    15,    16,    17,    18,     0,     0,    19,    20,
      21,    22,    23,    24,    25,    26,    27,    28,     0,     0,
       0,    29,    30,    31,    32,    33,     0,    34,     0,     0,
       0,    35,    36,    37,    38,    39,     0,    40,     0,    41,
       0,    42,     0,     0,    43,     0,     0,     0,    44,    45,
      46,    47,     0,    49,    50,     0,    51,     0,    53,     0,
       0,     0,     0,     0,    56,     0,    57,    58,    59,     0,
       0,     0,     0,     0,     0,     0,    63,    64,    65,    66,
      67,    68,    69,     0,     0,    70,    71,     0,    72,     0,
       0,     0,    73,     4,     5,     6,     7,     8,   124,    75,
      76,    77,     9,     0,     0,    79,    80,     0,     0,    81,
       0,    82,    83,     0,    84,     0,   790,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,    10,    11,     0,     0,     0,     0,
      12,     0,    13,    14,    15,    16,    17,    18,     0,     0,
      19,    20,    21,    22,    23,    24,    25,    26,    27,    28,
       0,     0,     0,    29,    30,    31,    32,    33,     0,    34,
       0,     0,     0,    35,    36,    37,    38,    39,     0,    40,
       0,    41,     0,    42,     0,     0,    43,     0,     0,     0,
      44,    45,    46,    47,     0,    49,    50,     0,    51,     0,
      53,     0,     0,     0,     0,     0,    56,     0,    57,    58,
      59,     0,     0,     0,     0,     0,     0,     0,    63,    64,
      65,    66,    67,    68,    69,     0,     0,    70,    71,     0,
      72,     0,     0,     0,    73,     4,     5,     6,     7,     8,
     124,    75,    76,    77,     9,     0,     0,    79,    80,     0,
       0,    81,     0,    82,    83,     0,    84,     0,   830,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,    10,    11,     0,     0,
       0,     0,    12,     0,    13,    14,    15,    16,    17,    18,
       0,     0,    19,    20,    21,    22,    23,    24,    25,    26,
      27,    28,     0,     0,     0,    29,    30,    31,    32,    33,
       0,    34,     0,     0,     0,    35,    36,    37,    38,    39,
       0,    40,     0,    41,     0,    42,     0,     0,    43,     0,
       0,     0,    44,    45,    46,    47,     0,    49,    50,     0,
      51,     0,    53,     0,     0,     0,     0,     0,    56,     0,
      57,    58,    59,     0,     0,     0,     0,     0,     0,     0,
      63,    64,    65,    66,    67,    68,    69,     0,     0,    70,
      71,     0,    72,     0,     0,     0,    73,     4,     5,     6,
       7,     8,   124,    75,    76,    77,     9,     0,     0,    79,
      80,     0,     0,    81,     0,    82,    83,     0,    84,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,    10,    11,
       0,     0,     0,     0,    12,     0,    13,    14,    15,    16,
      17,    18,     0,     0,    19,    20,    21,    22,    23,    24,
      25,    26,    27,    28,     0,     0,     0,    29,    30,    31,
      32,    33,     0,    34,     0,     0,     0,    35,    36,    37,
      38,    39,     0,    40,     0,    41,     0,    42,     0,     0,
      43,     0,     0,     0,    44,    45,    46,    47,     0,    49,
      50,     0,    51,     0,    53,     0,     0,     0,     0,     0,
      56,     0,    57,    58,    59,     0,     0,     0,     0,     0,
       0,     0,    63,    64,    65,    66,    67,    68,    69,     0,
       0,    70,    71,     0,    72,     0,     0,     0,    73,     4,
       5,     6,     7,     8,   124,    75,    76,    77,     9,     0,
       0,    79,    80,     0,     0,    81,     0,    82,    83,     0,
      84,     0,     0,     0,     0,     0,     0,     0,   384,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
      10,    11,     0,     0,     0,     0,    12,     0,    13,    14,
      15,    16,    17,    18,     0,     0,    19,    20,    21,    22,
      23,    24,    25,    26,    27,     0,     0,     0,     0,    29,
      30,   122,    32,    33,     0,     0,     0,     0,     0,    35,
      36,   249,   250,   251,   252,   253,   254,   255,   256,   257,
     258,   259,   260,   261,   262,   263,   264,   265,   266,    47,
     267,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,   123,     0,     0,    58,    59,     0,     0,     0,
       0,     0,     0,     0,    63,    64,    65,    66,    67,    68,
      69,     0,     4,     5,     6,     7,     8,     0,     0,     0,
      73,     9,     0,     0,     0,     0,   124,    75,    76,    77,
       0,     0,     0,    79,   125,     0,   385,    81,     0,     0,
       0,   156,    84,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,    10,    11,     0,     0,     0,     0,    12,
       0,    13,    14,    15,    16,    17,    18,     0,     0,    19,
      20,    21,    22,    23,    24,    25,    26,    27,     0,     0,
       0,     0,    29,    30,   122,    32,    33,     0,     0,     0,
       0,     0,    35,    36,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,    47,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,   123,     0,     0,    58,    59,
       0,     0,     0,     0,     0,     0,     0,    63,    64,    65,
      66,    67,    68,    69,     0,     4,     5,     6,     7,     8,
       0,     0,     0,    73,     9,     0,     0,     0,     0,   124,
      75,    76,    77,     0,     0,     0,    79,   125,     0,     0,
      81,     0,     0,     0,     0,    84,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,    10,    11,     0,     0,
       0,     0,    12,     0,    13,    14,    15,    16,    17,    18,
       0,     0,    19,    20,    21,    22,    23,    24,    25,    26,
      27,     0,     0,     0,     0,    29,    30,   122,    32,    33,
       0,     0,     0,     0,     0,    35,    36,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,    47,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,   123,     0,
       0,    58,    59,     0,     0,     0,     0,     0,     0,     0,
      63,    64,    65,    66,    67,    68,    69,     0,     4,     5,
       6,     7,     8,     0,     0,     0,    73,     9,     0,     0,
       0,     0,   124,    75,    76,    77,     0,     0,     0,    79,
     125,     0,     0,    81,     0,   186,     0,     0,    84,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,    10,
      11,     0,     0,     0,     0,    12,     0,    13,    14,    15,
      16,    17,    18,     0,     0,    19,    20,    21,    22,    23,
      24,    25,    26,    27,     0,     0,     0,     0,    29,    30,
     122,    32,    33,     0,     0,     0,     0,     0,    35,    36,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,    47,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,   123,     0,     0,    58,    59,     0,     0,     0,     0,
       0,     0,     0,    63,    64,    65,    66,    67,    68,    69,
       0,     4,     5,     6,     7,     8,     0,     0,     0,    73,
       9,     0,     0,     0,     0,   124,    75,    76,    77,     0,
       0,     0,    79,   125,     0,     0,    81,     0,   188,     0,
       0,    84,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,    10,    11,     0,     0,     0,     0,    12,     0,
      13,    14,    15,    16,    17,    18,     0,     0,    19,    20,
      21,    22,    23,    24,    25,    26,    27,     0,     0,     0,
       0,    29,    30,   122,    32,    33,     0,     0,     0,     0,
       0,    35,    36,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,    47,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,   123,     0,     0,    58,    59,     0,
       0,     0,     0,     0,     0,     0,    63,    64,    65,    66,
      67,    68,    69,     0,     4,     5,     6,     7,     8,     0,
       0,     0,    73,     9,     0,     0,     0,     0,   124,    75,
      76,    77,     0,     0,     0,    79,   125,     0,     0,    81,
       0,   192,     0,     0,    84,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,    10,    11,     0,     0,     0,
       0,    12,     0,    13,    14,    15,    16,    17,    18,     0,
       0,    19,    20,    21,    22,    23,    24,    25,    26,    27,
       0,     0,     0,     0,    29,    30,   122,    32,    33,     0,
       0,     0,     0,     0,    35,    36,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,    47,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,   123,     0,     0,
      58,    59,     0,     0,     0,     0,     0,     0,     0,    63,
      64,    65,    66,    67,    68,    69,     0,     4,     5,     6,
       7,     8,     0,     0,     0,    73,     9,     0,     0,     0,
       0,   124,    75,    76,    77,     0,     0,     0,    79,   125,
     405,     0,    81,   316,     0,     0,     0,    84,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,    10,    11,
       0,     0,     0,     0,    12,     0,    13,    14,    15,    16,
      17,    18,     0,     0,    19,    20,    21,    22,    23,    24,
      25,    26,    27,     0,     0,     0,     0,    29,    30,   122,
      32,    33,     0,     0,     0,     0,     0,    35,    36,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,    47,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
     123,     0,     0,    58,    59,     0,     0,     0,     0,     0,
       0,     0,    63,    64,    65,    66,    67,    68,    69,     0,
       4,     5,     6,     7,     8,     0,     0,     0,    73,     9,
       0,     0,     0,     0,   124,    75,    76,    77,     0,     0,
       0,    79,   125,     0,     0,    81,     0,     0,     0,   431,
      84,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,    10,    11,     0,     0,     0,     0,    12,     0,    13,
      14,    15,    16,    17,    18,     0,     0,    19,    20,    21,
      22,    23,    24,    25,    26,    27,     0,     0,     0,     0,
      29,    30,   122,    32,    33,     0,     0,     0,     0,     0,
      35,    36,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
      47,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,   123,     0,     0,    58,    59,     0,     0,
       0,     0,     0,     0,     0,    63,    64,    65,    66,    67,
      68,    69,     0,     4,     5,     6,     7,     8,     0,     0,
       0,    73,     9,     0,     0,     0,     0,   124,    75,    76,
      77,     0,     0,     0,    79,   125,     0,     0,    81,     0,
       0,     0,   463,    84,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,    10,    11,     0,     0,     0,     0,
      12,     0,    13,    14,    15,    16,    17,    18,     0,     0,
      19,    20,    21,    22,    23,    24,    25,    26,    27,     0,
       0,     0,     0,    29,    30,   122,    32,    33,     0,     0,
       0,     0,     0,    35,    36,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,    47,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,   123,     0,     0,    58,
      59,     0,     0,     0,     0,     0,     0,     0,    63,    64,
      65,    66,    67,    68,    69,     0,     4,     5,     6,     7,
       8,     0,     0,     0,    73,     9,     0,     0,     0,     0,
     124,    75,    76,    77,     0,     0,     0,    79,   125,     0,
       0,    81,     0,     0,     0,   465,    84,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,    10,    11,     0,
       0,     0,     0,    12,     0,    13,    14,    15,    16,    17,
      18,     0,     0,    19,    20,    21,    22,    23,    24,    25,
      26,    27,     0,     0,     0,     0,    29,    30,   122,    32,
      33,     0,     0,     0,     0,     0,    35,    36,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,    47,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,   123,
       0,     0,    58,    59,     0,     0,     0,     0,     0,     0,
       0,    63,    64,    65,    66,    67,    68,    69,     0,     4,
       5,     6,     7,     8,     0,     0,     0,    73,     9,     0,
       0,     0,     0,   124,    75,    76,    77,     0,     0,     0,
      79,   125,     0,     0,    81,     0,     0,     0,   657,    84,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
      10,    11,     0,     0,     0,     0,    12,     0,    13,    14,
      15,    16,    17,    18,     0,     0,    19,    20,    21,    22,
      23,    24,    25,    26,    27,     0,     0,     0,     0,    29,
      30,   122,    32,    33,     0,     0,     0,     0,     0,    35,
      36,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,    47,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,   123,     0,     0,    58,    59,     0,     0,     0,
       0,     0,     0,     0,    63,    64,    65,    66,    67,    68,
      69,     0,     4,     5,     6,     7,     8,     0,     0,     0,
      73,     9,     0,     0,     0,     0,   124,    75,    76,    77,
       0,     0,     0,    79,   125,     0,     0,    81,     0,     0,
       0,     0,    84,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,    10,    11,     0,     0,     0,     0,    12,
       0,    13,    14,    15,    16,    17,    18,     0,     0,    19,
      20,    21,    22,    23,    24,    25,    26,    27,     0,     0,
       0,     0,    29,    30,   122,    32,    33,     0,     0,     0,
       0,     0,    35,    36,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,    47,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,   123,     0,     0,    58,    59,
       0,     0,     0,     0,     0,     0,     0,    63,    64,    65,
      66,    67,    68,    69,     0,     4,     5,     6,     7,     8,
       0,     0,     0,    73,     9,     0,     0,     0,     0,   124,
      75,    76,    77,     0,     0,     0,    79,   125,     0,     0,
      81,     0,     0,     0,     0,    84,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,    10,    11,     0,     0,
       0,     0,    12,     0,    13,    14,    15,    16,    17,    18,
       0,     0,    19,    20,    21,    22,    23,    24,    25,    26,
      27,     0,     0,     0,     0,    29,    30,   122,    32,    33,
       0,     0,     0,     0,     0,    35,    36,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,    47,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,   123,     0,
       0,    58,    59,     0,     0,     0,     0,     0,     0,     0,
      63,    64,    65,    66,    67,    68,    69,     0,     0,     0,
       0,     0,     0,     0,     0,     0,    73,     0,     0,     0,
       0,     0,   124,    75,    76,    77,   240,   241,   242,    79,
      80,     0,     0,    81,     0,     0,     0,     0,    84,     0,
       0,     0,   243,     0,   244,   245,   246,   247,   248,   249,
     250,   251,   252,   253,   254,   255,   256,   257,   258,   259,
     260,   261,   262,   263,   264,   265,   266,     0,   267,   240,
     241,   242,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,   243,     0,   244,   245,   246,
     247,   248,   249,   250,   251,   252,   253,   254,   255,   256,
     257,   258,   259,   260,   261,   262,   263,   264,   265,   266,
       0,   267,   240,   241,   242,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,   243,     0,
     244,   245,   246,   247,   248,   249,   250,   251,   252,   253,
     254,   255,   256,   257,   258,   259,   260,   261,   262,   263,
     264,   265,   266,     0,   267,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,   240,   241,   242,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,   243,   570,   244,   245,   246,   247,   248,   249,   250,
     251,   252,   253,   254,   255,   256,   257,   258,   259,   260,
     261,   262,   263,   264,   265,   266,     0,   267,     0,     0,
     240,   241,   242,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,   615,   243,   798,   244,   245,
     246,   247,   248,   249,   250,   251,   252,   253,   254,   255,
     256,   257,   258,   259,   260,   261,   262,   263,   264,   265,
     266,     0,   267,   240,   241,   242,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,   650,   243,
       0,   244,   245,   246,   247,   248,   249,   250,   251,   252,
     253,   254,   255,   256,   257,   258,   259,   260,   261,   262,
     263,   264,   265,   266,     0,   267,     0,     0,     0,     0,
       0,     0,     0,     0,   240,   241,   242,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
     243,   733,   244,   245,   246,   247,   248,   249,   250,   251,
     252,   253,   254,   255,   256,   257,   258,   259,   260,   261,
     262,   263,   264,   265,   266,     0,   267,   240,   241,   242,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,   243,   799,   244,   245,   246,   247,   248,
     249,   250,   251,   252,   253,   254,   255,   256,   257,   258,
     259,   260,   261,   262,   263,   264,   265,   266,     0,   267,
     240,   241,   242,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,   243,   268,   244,   245,
     246,   247,   248,   249,   250,   251,   252,   253,   254,   255,
     256,   257,   258,   259,   260,   261,   262,   263,   264,   265,
     266,     0,   267,     0,     0,     0,     0,     0,     0,     0,
       0,   240,   241,   242,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,   243,   334,   244,
     245,   246,   247,   248,   249,   250,   251,   252,   253,   254,
     255,   256,   257,   258,   259,   260,   261,   262,   263,   264,
     265,   266,     0,   267,   240,   241,   242,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
     243,   335,   244,   245,   246,   247,   248,   249,   250,   251,
     252,   253,   254,   255,   256,   257,   258,   259,   260,   261,
     262,   263,   264,   265,   266,     0,   267,   240,   241,   242,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,   243,   341,   244,   245,   246,   247,   248,
     249,   250,   251,   252,   253,   254,   255,   256,   257,   258,
     259,   260,   261,   262,   263,   264,   265,   266,     0,   267,
       0,     0,     0,     0,     0,     0,     0,   240,   241,   242,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,   243,   374,   244,   245,   246,   247,   248,
     249,   250,   251,   252,   253,   254,   255,   256,   257,   258,
     259,   260,   261,   262,   263,   264,   265,   266,     0,   267,
     240,   241,   242,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,   243,   459,   244,   245,
     246,   247,   248,   249,   250,   251,   252,   253,   254,   255,
     256,   257,   258,   259,   260,   261,   262,   263,   264,   265,
     266,     0,   267,   240,   241,   242,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,   243,
     473,   244,   245,   246,   247,   248,   249,   250,   251,   252,
     253,   254,   255,   256,   257,   258,   259,   260,   261,   262,
     263,   264,   265,   266,     0,   267,     0,     0,     0,     0,
       0,     0,     0,   240,   241,   242,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,   243,
     474,   244,   245,   246,   247,   248,   249,   250,   251,   252,
     253,   254,   255,   256,   257,   258,   259,   260,   261,   262,
     263,   264,   265,   266,     0,   267,   240,   241,   242,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,   243,   479,   244,   245,   246,   247,   248,   249,
     250,   251,   252,   253,   254,   255,   256,   257,   258,   259,
     260,   261,   262,   263,   264,   265,   266,     0,   267,   240,
     241,   242,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,   243,   487,   244,   245,   246,
     247,   248,   249,   250,   251,   252,   253,   254,   255,   256,
     257,   258,   259,   260,   261,   262,   263,   264,   265,   266,
       0,   267,     0,     0,     0,     0,     0,     0,     0,   240,
     241,   242,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,   243,   664,   244,   245,   246,
     247,   248,   249,   250,   251,   252,   253,   254,   255,   256,
     257,   258,   259,   260,   261,   262,   263,   264,   265,   266,
       0,   267,   240,   241,   242,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,   243,   865,
     244,   245,   246,   247,   248,   249,   250,   251,   252,   253,
     254,   255,   256,   257,   258,   259,   260,   261,   262,   263,
     264,   265,   266,     0,   267,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,   886,   240,   241,   242,     0,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,   303,     0,   243,
       0,   244,   245,   246,   247,   248,   249,   250,   251,   252,
     253,   254,   255,   256,   257,   258,   259,   260,   261,   262,
     263,   264,   265,   266,     0,   267,   488,   489,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
     372,     0,     0,     0,     0,     0,     0,   490,     0,     0,
     240,   241,   242,     0,     0,    29,    30,   145,     0,     0,
       0,     0,     0,     0,     0,   491,   243,   555,   244,   245,
     246,   247,   248,   249,   250,   251,   252,   253,   254,   255,
     256,   257,   258,   259,   260,   261,   262,   263,   264,   265,
     266,     0,   267,     0,     0,     0,     0,     0,   146,     0,
       0,   576,     0,     0,     0,     0,     0,     0,     0,     0,
       0,   492,    65,    66,    67,    68,    69,     0,     0,     0,
       0,     0,   240,   241,   242,     0,    73,     0,     0,     0,
       0,     0,   493,    75,    76,   494,     0,     0,   243,    79,
     244,   245,   246,   247,   248,   249,   250,   251,   252,   253,
     254,   255,   256,   257,   258,   259,   260,   261,   262,   263,
     264,   265,   266,     0,   267,   241,   242,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
     243,     0,   244,   245,   246,   247,   248,   249,   250,   251,
     252,   253,   254,   255,   256,   257,   258,   259,   260,   261,
     262,   263,   264,   265,   266,   242,   267,     0,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,   243,
       0,   244,   245,   246,   247,   248,   249,   250,   251,   252,
     253,   254,   255,   256,   257,   258,   259,   260,   261,   262,
     263,   264,   265,   266,     0,   267,   244,   245,   246,   247,
     248,   249,   250,   251,   252,   253,   254,   255,   256,   257,
     258,   259,   260,   261,   262,   263,   264,   265,   266,     0,
     267,   246,   247,   248,   249,   250,   251,   252,   253,   254,
     255,   256,   257,   258,   259,   260,   261,   262,   263,   264,
     265,   266,     0,   267,   247,   248,   249,   250,   251,   252,
     253,   254,   255,   256,   257,   258,   259,   260,   261,   262,
     263,   264,   265,   266,     0,   267,   248,   249,   250,   251,
     252,   253,   254,   255,   256,   257,   258,   259,   260,   261,
     262,   263,   264,   265,   266,     0,   267
};

static const yytype_int16 yycheck[] =
{
       2,   126,    26,   166,     2,   314,     2,    22,    23,    49,
     177,    26,   508,    26,   196,   267,   384,     2,   391,   239,
     393,   480,   717,   712,     8,   218,     8,   640,   639,     8,
      77,     8,    65,     8,     8,     8,     8,    52,     8,   283,
      80,     8,    38,    77,   107,   541,    22,    23,    65,     8,
      26,     8,     8,    65,    56,     8,    65,    26,     0,    74,
      26,    26,    77,   307,   284,   760,   286,    75,    75,   153,
      75,   238,    77,   153,    77,   633,    95,   161,    32,   299,
     300,   161,    81,    32,    32,   210,    26,    75,   161,    77,
     161,   311,   147,   313,   314,   463,   159,   465,   153,    65,
     103,   659,   115,   161,    25,   718,    13,    14,    15,    16,
      17,    18,    19,    20,    21,    22,    23,    24,   285,   166,
     287,   123,    65,    77,   291,   292,   293,   371,    77,    77,
     147,   164,   166,   148,   153,   147,    65,    75,   161,    77,
      77,   156,    25,   183,   161,   598,   153,   164,   163,    75,
     149,    77,   164,   161,    75,   164,    63,    64,   171,   164,
     771,   166,   775,   536,    13,    14,    15,    16,    17,    18,
      19,    20,    21,    22,    23,    24,    77,   370,   166,   163,
     156,   163,   164,   198,   163,   874,   163,   162,   203,   163,
     163,   163,    75,   163,   163,   162,   211,   212,   213,   164,
     215,    95,   217,   162,    75,   162,   162,   666,   129,   162,
      75,   160,   160,   908,    63,    64,   161,    75,   161,    32,
     673,   164,   675,   160,   122,   227,   239,   203,   166,   150,
      98,    99,   153,    32,   155,   211,   212,   213,   153,   215,
     166,   162,    75,   267,    77,   161,   129,   161,   163,   164,
     161,    75,   267,    77,   267,   162,   163,    98,    99,   153,
     147,   634,    75,    75,    77,   166,   153,   150,   153,    67,
     153,   284,   155,   286,   161,   519,   161,    75,    77,    77,
      75,   296,   153,   116,   147,   150,   299,   300,   153,   657,
     153,   267,   150,   164,   309,   153,   147,   165,   311,   239,
     313,   314,   153,   116,   556,    63,    64,   551,   552,   164,
     161,   684,   122,   162,   163,   559,   560,   150,   116,   563,
     153,   161,   337,   161,   165,   161,   699,   267,   161,   344,
     161,   164,   372,   166,   162,   163,   351,   150,   340,    75,
     153,    77,   166,   161,   284,   161,   286,    75,   161,    75,
     147,   660,   150,   166,    75,   153,   153,   162,   163,   299,
     300,   337,   164,   161,   161,    75,   368,   163,   166,   384,
     368,   311,   368,   313,   314,   351,   391,    32,   393,   147,
     116,   396,   104,   368,    65,   153,   122,   109,   401,   111,
     112,   113,   114,   115,   116,   117,    70,    71,   580,   147,
     567,   583,   898,   129,   161,    47,    48,    49,   384,    51,
      70,    71,   594,    75,   150,    77,   431,   153,    97,    98,
      99,   730,   163,   164,   150,   161,    65,   153,   147,   155,
     166,    97,    98,    99,   807,   126,   809,    22,    23,    65,
     660,    65,    65,   165,    65,   103,   153,    51,   463,   103,
     465,   153,    67,   147,   116,   431,   167,   111,   112,   113,
     114,   115,   116,   147,   161,     8,   128,   482,   483,   484,
      75,   844,    77,   488,   489,   490,   153,   153,   474,   494,
     147,   126,   147,   479,   647,   652,   653,   463,   150,   465,
     486,   153,    87,   508,   509,    75,   511,    13,    13,   161,
     515,   516,   875,   685,   166,   162,   482,   483,   484,   162,
     730,   116,   488,   489,   490,   163,   531,   163,   533,   521,
      75,   536,   162,   521,    75,   521,   541,   542,   124,   161,
     124,   904,   556,   509,   167,   511,   521,   167,   163,   515,
     516,   556,    75,   556,    77,   150,     8,   161,   153,   111,
     112,   113,   114,   115,   116,   531,   161,    95,    13,    77,
     104,   166,    75,   161,   163,   109,     8,   111,   112,   113,
     114,   115,   116,   117,   589,   162,   162,   161,   161,    13,
     556,   162,   164,   116,   125,   161,   161,   161,   167,   162,
     605,   606,    44,    45,    46,    47,    48,    49,   161,    51,
     161,   616,   167,   161,    75,   153,    75,   167,   790,   161,
     167,   167,   162,   589,   167,   630,   556,   150,   800,   634,
     153,   165,   147,   163,   639,    13,    13,   162,   161,   605,
     162,   153,     8,   166,   164,   817,   162,     8,    65,   821,
     616,    65,   657,   164,   126,   827,   163,   660,   830,   127,
      13,   163,   667,   835,   163,   127,   671,   839,     8,   167,
     662,    75,   677,   665,   679,   161,   104,   164,   162,   684,
     672,   109,   162,   111,   112,   113,   114,   115,   116,   117,
     163,   657,   162,   109,   699,    77,    13,   869,   162,   814,
     126,   667,   162,    75,   162,   671,   162,   162,    77,   163,
     167,   677,   162,   679,   162,   162,   721,    26,   161,   711,
      22,    23,    13,   167,    26,   161,   167,   730,   900,   163,
     660,   162,   127,   163,   163,    77,    13,   165,    13,   911,
      75,    22,    23,   164,   164,    13,   161,    26,    72,    77,
     163,   162,   164,   739,    13,   721,    77,    13,   163,   745,
     746,   164,    95,   165,   163,    95,   771,   759,    49,   154,
     163,   147,   777,    13,    75,     4,     5,   163,     7,     8,
       9,    10,    11,    12,    13,    14,    15,    16,    17,    18,
      19,    20,    21,    75,   161,    24,    25,   802,    26,    80,
     730,   806,   807,   789,   809,    77,   811,   163,    37,    75,
      75,   777,     8,   162,   611,    44,    45,   344,   823,   509,
      49,   611,    51,   308,    38,    39,    40,    41,    42,    43,
      44,    45,    46,    47,    48,    49,   802,    51,   536,   844,
     806,   833,   468,   667,   836,   811,   590,   746,   542,   854,
     530,    80,    81,   349,   156,   860,   797,   823,    81,   844,
     605,   690,   604,   855,   203,   516,   781,   859,   216,   515,
     875,   863,    -1,   878,   866,   156,   868,    -1,   870,   865,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,   854,    -1,
      -1,    -1,    -1,   898,   860,    -1,    -1,    -1,   890,   904,
     129,   203,   183,    -1,    -1,    -1,    -1,    -1,    -1,   211,
     212,   213,   878,   215,   906,    -1,    -1,    -1,    -1,   337,
      -1,   913,   203,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
     211,   212,   213,   351,   215,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,   172,    -1,   174,    -1,   176,   177,    -1,
      -1,    -1,   181,   182,   183,    -1,   185,   337,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,   267,    -1,    -1,    -1,    -1,
      -1,   351,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,   216,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,   228,
      -1,   230,    -1,    -1,    -1,    -1,    -1,    -1,    -1,   238,
      -1,   240,   241,   242,   243,   244,   245,   246,   247,   248,
     249,   250,   251,   252,   253,   254,   255,   256,   257,   258,
     259,   260,   261,   262,   263,   264,   265,   266,    -1,    -1,
      -1,    -1,   271,   272,   273,   274,   275,   276,   277,   278,
     279,   280,   281,   282,   283,    -1,   285,    -1,   287,   288,
      -1,    -1,   291,   292,   293,    -1,   484,    -1,    -1,    -1,
     488,   489,   490,    -1,   303,    -1,   305,    -1,   307,    -1,
      -1,    -1,   384,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,   372,    -1,   322,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,   384,   484,    -1,    -1,    -1,   488,   489,
      -1,    -1,    -1,   531,    -1,    -1,    -1,   346,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,   431,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,   371,   372,    -1,    -1,    -1,    -1,    -1,    -1,
     431,   531,    -1,    -1,    -1,    -1,   385,    -1,    -1,    -1,
      -1,   463,    -1,   465,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,   405,    -1,    -1,    -1,
     482,   483,   463,    -1,   465,    -1,    -1,   605,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,   616,    -1,
      -1,   482,   483,    -1,    -1,    -1,    -1,   509,    -1,   511,
      -1,    -1,    -1,   515,   516,    -1,    -1,    -1,    -1,    -1,
     449,    -1,    -1,    -1,    -1,    -1,    -1,    -1,   509,    -1,
     511,    -1,    -1,    -1,   515,   516,   616,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,     9,    10,    11,    -1,    -1,   478,
      -1,   480,   481,   671,   556,    -1,    -1,    -1,    -1,   677,
      25,   679,    27,    28,    29,    30,    31,    32,    33,    34,
      35,    36,    37,    38,    39,    40,    41,    42,    43,    44,
      45,    46,    47,    48,    49,    -1,    51,   589,    -1,    -1,
     519,   671,    -1,    -1,    -1,    -1,    -1,   677,    -1,   679,
      -1,   530,    -1,   721,    -1,    -1,    -1,    -1,   589,    -1,
      13,    14,    15,    16,    17,    18,    19,    20,    21,    22,
      23,    24,   551,   552,    -1,    -1,   555,    -1,    -1,    -1,
     559,   560,    -1,    -1,   563,    -1,    -1,    -1,   567,   568,
      -1,   721,    -1,    -1,    -1,    -1,    -1,   576,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,   657,    -1,    -1,    -1,   777,
      63,    64,    -1,    -1,    -1,   667,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,   657,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,   802,    -1,   667,    -1,   806,    -1,
      -1,    -1,    95,   811,   623,    -1,    -1,   777,    -1,    -1,
     165,    -1,    -1,    -1,    -1,   823,    -1,    -1,    13,    14,
      15,    16,    17,    18,    19,    20,    21,    22,    23,    24,
      -1,    -1,   802,   652,   653,    -1,   806,    -1,    -1,    -1,
      -1,   811,    -1,    -1,    -1,    -1,   854,   666,    -1,    -1,
      -1,    -1,   860,   823,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,     3,     4,     5,     6,     7,    63,    64,
     878,    -1,    12,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,   854,    -1,    -1,    -1,    -1,    -1,
     860,    13,    14,    15,    16,    17,    18,    19,    20,    21,
      22,    23,    24,    -1,    44,    45,    -1,    -1,   878,    -1,
      50,    -1,    52,    53,    54,    55,    56,    57,    -1,    -1,
      60,    61,    62,    63,    64,    65,    66,    67,    68,    69,
      -1,    -1,   751,    73,    74,    75,    76,    77,    -1,    79,
      -1,    63,    64,    83,    84,    85,    86,    87,    -1,    89,
      -1,    91,    -1,    93,    -1,    -1,    96,    -1,    -1,    -1,
     100,   101,   102,   103,   104,   105,   106,   786,   108,   109,
     110,    -1,    -1,    -1,   114,   115,   116,    -1,   118,   119,
     120,   121,   122,   123,    -1,    -1,    -1,    -1,   128,   129,
     130,   131,   132,   133,   134,    -1,    -1,   137,   138,    -1,
     140,    -1,    -1,    -1,   144,    -1,    -1,   826,    -1,    -1,
     150,   151,   152,   153,    -1,    -1,   156,   157,   158,    -1,
      -1,   161,    -1,   163,   164,   165,   166,     3,     4,     5,
       6,     7,    -1,    -1,    -1,    -1,    12,    -1,    -1,    -1,
      25,   163,    27,    28,    29,    30,    31,    32,    33,    34,
      35,    36,    37,    38,    39,    40,    41,    42,    43,    44,
      45,    46,    47,    48,    49,    -1,    51,    -1,    44,    45,
      -1,    -1,    -1,    -1,    50,    -1,    52,    53,    54,    55,
      56,    57,    -1,    -1,    60,    61,    62,    63,    64,    65,
      66,    67,    68,    69,    -1,    -1,    -1,    73,    74,    75,
      76,    77,    -1,    79,    -1,    -1,    -1,    83,    84,    85,
      86,    87,    -1,    89,    -1,    91,    -1,    93,    -1,    -1,
      96,    -1,    -1,    -1,   100,   101,   102,   103,   104,   105,
     106,    -1,   108,   109,   110,    -1,    -1,    -1,   114,   115,
     116,    -1,   118,   119,   120,   121,   122,   123,    -1,    -1,
      -1,    -1,   128,   129,   130,   131,   132,   133,   134,    -1,
      -1,   137,   138,    -1,   140,    -1,    -1,    -1,   144,     3,
       4,     5,     6,     7,   150,   151,   152,   153,    12,    -1,
     156,   157,   158,    -1,    -1,   161,    -1,   163,   164,   165,
     166,    33,    34,    35,    36,    37,    38,    39,    40,    41,
      42,    43,    44,    45,    46,    47,    48,    49,    -1,    51,
      44,    45,    -1,    -1,    -1,    -1,    50,    -1,    52,    53,
      54,    55,    56,    57,    -1,    -1,    60,    61,    62,    63,
      64,    65,    66,    67,    68,    69,    -1,    -1,    -1,    73,
      74,    75,    76,    77,    -1,    79,    -1,    -1,    -1,    83,
      84,    85,    86,    87,    -1,    89,    -1,    91,    -1,    93,
      -1,    -1,    96,    -1,    -1,    -1,   100,   101,   102,   103,
     104,   105,   106,    -1,   108,   109,   110,    -1,    -1,    -1,
     114,   115,   116,    -1,   118,   119,   120,   121,   122,   123,
      -1,    -1,    -1,    -1,   128,   129,   130,   131,   132,   133,
     134,    -1,    -1,   137,   138,    -1,   140,    -1,    -1,    -1,
     144,     3,     4,     5,     6,     7,   150,   151,   152,   153,
      12,    -1,   156,   157,   158,    -1,    -1,   161,    -1,   163,
     164,    -1,   166,    33,    34,    35,    36,    37,    38,    39,
      40,    41,    42,    43,    44,    45,    46,    47,    48,    49,
      -1,    51,    44,    45,    -1,    -1,    -1,    -1,    50,    -1,
      52,    53,    54,    55,    56,    57,    -1,    -1,    60,    61,
      62,    63,    64,    65,    66,    67,    68,    69,    -1,    -1,
      -1,    73,    74,    75,    76,    77,    -1,    79,    -1,    -1,
      -1,    83,    84,    85,    86,    87,    -1,    89,    -1,    91,
      -1,    93,    -1,    -1,    96,    -1,    -1,    -1,   100,   101,
     102,   103,    -1,   105,   106,    -1,   108,    -1,   110,    -1,
      -1,    -1,   114,   115,   116,    -1,   118,   119,   120,   121,
     122,   123,    -1,    -1,    -1,    -1,   128,   129,   130,   131,
     132,   133,   134,    -1,    -1,   137,   138,    -1,   140,    -1,
      -1,    -1,   144,     3,     4,     5,     6,     7,   150,   151,
     152,   153,    12,    -1,   156,   157,   158,    -1,    -1,   161,
      -1,   163,   164,   165,   166,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    44,    45,    -1,    -1,    -1,    -1,
      50,    -1,    52,    53,    54,    55,    56,    57,    -1,    -1,
      60,    61,    62,    63,    64,    65,    66,    67,    68,    69,
      -1,    -1,    -1,    73,    74,    75,    76,    77,    -1,    79,
      -1,    -1,    -1,    83,    84,    85,    86,    87,    -1,    89,
      -1,    91,    -1,    93,    -1,    -1,    96,    -1,    -1,    -1,
     100,   101,   102,   103,    -1,   105,   106,    -1,   108,    -1,
     110,    -1,    -1,    -1,   114,   115,   116,    -1,   118,   119,
     120,   121,   122,   123,    -1,    -1,    -1,    -1,   128,   129,
     130,   131,   132,   133,   134,    -1,    -1,   137,   138,    -1,
     140,    -1,    -1,    -1,   144,     3,     4,     5,     6,     7,
     150,   151,   152,   153,    12,    -1,   156,   157,   158,    -1,
      -1,   161,    -1,   163,   164,   165,   166,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    44,    45,    -1,    -1,
      -1,    -1,    50,    -1,    52,    53,    54,    55,    56,    57,
      -1,    -1,    60,    61,    62,    63,    64,    65,    66,    67,
      68,    69,    -1,    -1,    -1,    73,    74,    75,    76,    77,
      -1,    79,    -1,    -1,    -1,    83,    84,    85,    86,    87,
      88,    89,    -1,    91,    -1,    93,    -1,    -1,    96,    -1,
      -1,    -1,   100,   101,   102,   103,    -1,   105,   106,    -1,
     108,    -1,   110,    -1,    -1,    -1,   114,   115,   116,    -1,
     118,   119,   120,   121,   122,   123,    -1,    -1,    -1,    -1,
     128,   129,   130,   131,   132,   133,   134,    -1,    -1,   137,
     138,    -1,   140,    -1,    -1,    -1,   144,     3,     4,     5,
       6,     7,   150,   151,   152,   153,    12,    -1,   156,   157,
     158,    -1,    -1,   161,    -1,   163,   164,    -1,   166,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    44,    45,
      -1,    -1,    -1,    -1,    50,    -1,    52,    53,    54,    55,
      56,    57,    -1,    -1,    60,    61,    62,    63,    64,    65,
      66,    67,    68,    69,    -1,    -1,    -1,    73,    74,    75,
      76,    77,    -1,    79,    -1,    -1,    -1,    83,    84,    85,
      86,    87,    -1,    89,    -1,    91,    -1,    93,    94,    -1,
      96,    -1,    -1,    -1,   100,   101,   102,   103,    -1,   105,
     106,    -1,   108,    -1,   110,    -1,    -1,    -1,   114,   115,
     116,    -1,   118,   119,   120,   121,   122,   123,    -1,    -1,
      -1,    -1,   128,   129,   130,   131,   132,   133,   134,    -1,
      -1,   137,   138,    -1,   140,    -1,    -1,    -1,   144,     3,
       4,     5,     6,     7,   150,   151,   152,   153,    12,    -1,
     156,   157,   158,    -1,    -1,   161,    -1,   163,   164,    -1,
     166,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      44,    45,    -1,    -1,    -1,    -1,    50,    -1,    52,    53,
      54,    55,    56,    57,    -1,    -1,    60,    61,    62,    63,
      64,    65,    66,    67,    68,    69,    -1,    -1,    -1,    73,
      74,    75,    76,    77,    -1,    79,    -1,    -1,    -1,    83,
      84,    85,    86,    87,    -1,    89,    -1,    91,    -1,    93,
      -1,    -1,    96,    -1,    -1,    -1,   100,   101,   102,   103,
      -1,   105,   106,    -1,   108,    -1,   110,    -1,    -1,    -1,
     114,   115,   116,    -1,   118,   119,   120,   121,   122,   123,
      -1,    -1,    -1,    -1,   128,   129,   130,   131,   132,   133,
     134,    -1,    -1,   137,   138,    -1,   140,    -1,    -1,    -1,
     144,     3,     4,     5,     6,     7,   150,   151,   152,   153,
      12,    -1,   156,   157,   158,    -1,    -1,   161,    -1,   163,
     164,   165,   166,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    44,    45,    -1,    -1,    -1,    -1,    50,    -1,
      52,    53,    54,    55,    56,    57,    -1,    -1,    60,    61,
      62,    63,    64,    65,    66,    67,    68,    69,    -1,    -1,
      -1,    73,    74,    75,    76,    77,    -1,    79,    -1,    -1,
      -1,    83,    84,    85,    86,    87,    -1,    89,    -1,    91,
      92,    93,    -1,    -1,    96,    -1,    -1,    -1,   100,   101,
     102,   103,    -1,   105,   106,    -1,   108,    -1,   110,    -1,
      -1,    -1,   114,   115,   116,    -1,   118,   119,   120,   121,
     122,   123,    -1,    -1,    -1,    -1,   128,   129,   130,   131,
     132,   133,   134,    -1,    -1,   137,   138,    -1,   140,    -1,
      -1,    -1,   144,     3,     4,     5,     6,     7,   150,   151,
     152,   153,    12,    -1,   156,   157,   158,    -1,    -1,   161,
      -1,   163,   164,    -1,   166,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    44,    45,    -1,    -1,    -1,    -1,
      50,    -1,    52,    53,    54,    55,    56,    57,    -1,    -1,
      60,    61,    62,    63,    64,    65,    66,    67,    68,    69,
      -1,    -1,    -1,    73,    74,    75,    76,    77,    -1,    79,
      -1,    -1,    -1,    83,    84,    85,    86,    87,    -1,    89,
      -1,    91,    -1,    93,    -1,    -1,    96,    -1,    -1,    -1,
     100,   101,   102,   103,    -1,   105,   106,    -1,   108,    -1,
     110,    -1,    -1,    -1,   114,   115,   116,    -1,   118,   119,
     120,   121,   122,   123,    -1,    -1,    -1,    -1,   128,   129,
     130,   131,   132,   133,   134,    -1,    -1,   137,   138,    -1,
     140,    -1,    -1,    -1,   144,     3,     4,     5,     6,     7,
     150,   151,   152,   153,    12,    -1,   156,   157,   158,    -1,
      -1,   161,    -1,   163,   164,   165,   166,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    44,    45,    -1,    -1,
      -1,    -1,    50,    -1,    52,    53,    54,    55,    56,    57,
      -1,    -1,    60,    61,    62,    63,    64,    65,    66,    67,
      68,    69,    -1,    -1,    -1,    73,    74,    75,    76,    77,
      -1,    79,    -1,    -1,    -1,    83,    84,    85,    86,    87,
      -1,    89,    -1,    91,    -1,    93,    -1,    -1,    96,    -1,
      -1,    -1,   100,   101,   102,   103,    -1,   105,   106,    -1,
     108,    -1,   110,    -1,    -1,    -1,   114,   115,   116,    -1,
     118,   119,   120,   121,   122,   123,    -1,    -1,    -1,    -1,
     128,   129,   130,   131,   132,   133,   134,    -1,    -1,   137,
     138,    -1,   140,    -1,    -1,    -1,   144,     3,     4,     5,
       6,     7,   150,   151,   152,   153,    12,    -1,   156,   157,
     158,    -1,    -1,   161,    -1,   163,   164,   165,   166,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    44,    45,
      -1,    -1,    -1,    -1,    50,    -1,    52,    53,    54,    55,
      56,    57,    -1,    -1,    60,    61,    62,    63,    64,    65,
      66,    67,    68,    69,    -1,    -1,    -1,    73,    74,    75,
      76,    77,    -1,    79,    -1,    -1,    -1,    83,    84,    85,
      86,    87,    -1,    89,    90,    91,    -1,    93,    -1,    -1,
      96,    -1,    -1,    -1,   100,   101,   102,   103,    -1,   105,
     106,    -1,   108,    -1,   110,    -1,    -1,    -1,   114,   115,
     116,    -1,   118,   119,   120,   121,   122,   123,    -1,    -1,
      -1,    -1,   128,   129,   130,   131,   132,   133,   134,    -1,
      -1,   137,   138,    -1,   140,    -1,    -1,    -1,   144,     3,
       4,     5,     6,     7,   150,   151,   152,   153,    12,    -1,
     156,   157,   158,    -1,    -1,   161,    -1,   163,   164,    -1,
     166,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      44,    45,    -1,    -1,    -1,    -1,    50,    -1,    52,    53,
      54,    55,    56,    57,    -1,    -1,    60,    61,    62,    63,
      64,    65,    66,    67,    68,    69,    -1,    -1,    -1,    73,
      74,    75,    76,    77,    -1,    79,    -1,    -1,    -1,    83,
      84,    85,    86,    87,    -1,    89,    -1,    91,    -1,    93,
      -1,    -1,    96,    -1,    -1,    -1,   100,   101,   102,   103,
      -1,   105,   106,    -1,   108,    -1,   110,    -1,    -1,    -1,
     114,   115,   116,    -1,   118,   119,   120,   121,   122,   123,
      -1,    -1,    -1,    -1,   128,   129,   130,   131,   132,   133,
     134,    -1,    -1,   137,   138,    -1,   140,    -1,    -1,    -1,
     144,     3,     4,     5,     6,     7,   150,   151,   152,   153,
      12,    -1,   156,   157,   158,    -1,    -1,   161,    -1,   163,
     164,   165,   166,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    44,    45,    -1,    -1,    -1,    -1,    50,    -1,
      52,    53,    54,    55,    56,    57,    -1,    -1,    60,    61,
      62,    63,    64,    65,    66,    67,    68,    69,    -1,    -1,
      -1,    73,    74,    75,    76,    77,    -1,    79,    -1,    -1,
      -1,    83,    84,    85,    86,    87,    -1,    89,    -1,    91,
      -1,    93,    -1,    -1,    96,    -1,    -1,    -1,   100,   101,
     102,   103,    -1,   105,   106,    -1,   108,    -1,   110,    -1,
      -1,    -1,   114,   115,   116,    -1,   118,   119,   120,   121,
     122,   123,    -1,    -1,    -1,    -1,   128,   129,   130,   131,
     132,   133,   134,    -1,    -1,   137,   138,    -1,   140,    -1,
      -1,    -1,   144,     3,     4,     5,     6,     7,   150,   151,
     152,   153,    12,    -1,   156,   157,   158,    -1,    -1,   161,
      -1,   163,   164,   165,   166,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    44,    45,    -1,    -1,    -1,    -1,
      50,    -1,    52,    53,    54,    55,    56,    57,    -1,    -1,
      60,    61,    62,    63,    64,    65,    66,    67,    68,    69,
      -1,    -1,    -1,    73,    74,    75,    76,    77,    -1,    79,
      -1,    -1,    -1,    83,    84,    85,    86,    87,    -1,    89,
      -1,    91,    -1,    93,    -1,    -1,    96,    -1,    -1,    -1,
     100,   101,   102,   103,    -1,   105,   106,    -1,   108,    -1,
     110,    -1,    -1,    -1,   114,   115,   116,    -1,   118,   119,
     120,   121,   122,   123,    -1,    -1,    -1,    -1,   128,   129,
     130,   131,   132,   133,   134,    -1,    -1,   137,   138,    -1,
     140,    -1,    -1,    -1,   144,     3,     4,     5,     6,     7,
     150,   151,   152,   153,    12,    -1,   156,   157,   158,    -1,
      -1,   161,    -1,   163,   164,   165,   166,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    44,    45,    -1,    -1,
      -1,    -1,    50,    -1,    52,    53,    54,    55,    56,    57,
      -1,    -1,    60,    61,    62,    63,    64,    65,    66,    67,
      68,    69,    -1,    -1,    -1,    73,    74,    75,    76,    77,
      -1,    79,    -1,    -1,    -1,    83,    84,    85,    86,    87,
      -1,    89,    -1,    91,    -1,    93,    -1,    -1,    96,    -1,
      -1,    -1,   100,   101,   102,   103,    -1,   105,   106,    -1,
     108,    -1,   110,    -1,    -1,    -1,   114,   115,   116,    -1,
     118,   119,   120,   121,   122,   123,    -1,    -1,    -1,    -1,
     128,   129,   130,   131,   132,   133,   134,    -1,    -1,   137,
     138,    -1,   140,    -1,    -1,    -1,   144,     3,     4,     5,
       6,     7,   150,   151,   152,   153,    12,    -1,   156,   157,
     158,    -1,    -1,   161,    -1,   163,   164,    -1,   166,    -1,
      26,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    44,    45,
      -1,    -1,    -1,    -1,    50,    -1,    52,    53,    54,    55,
      56,    57,    -1,    -1,    60,    61,    62,    63,    64,    65,
      66,    67,    68,    69,    -1,    -1,    -1,    73,    74,    75,
      76,    77,    -1,    79,    -1,    -1,    -1,    83,    84,    85,
      86,    87,    -1,    89,    -1,    91,    -1,    93,    -1,    -1,
      96,    -1,    -1,    -1,   100,   101,   102,   103,    -1,   105,
     106,    -1,   108,    -1,   110,    -1,    -1,    -1,    -1,    -1,
     116,    -1,   118,   119,   120,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,   128,   129,   130,   131,   132,   133,   134,    -1,
      -1,   137,   138,    -1,   140,    -1,    -1,    -1,   144,     3,
       4,     5,     6,     7,   150,   151,   152,   153,    12,    -1,
      -1,   157,   158,    -1,    -1,   161,    -1,   163,   164,    -1,
     166,    -1,    26,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      44,    45,    -1,    -1,    -1,    -1,    50,    -1,    52,    53,
      54,    55,    56,    57,    -1,    -1,    60,    61,    62,    63,
      64,    65,    66,    67,    68,    69,    -1,    -1,    -1,    73,
      74,    75,    76,    77,    -1,    79,    -1,    -1,    -1,    83,
      84,    85,    86,    87,    -1,    89,    -1,    91,    -1,    93,
      -1,    -1,    96,    -1,    -1,    -1,   100,   101,   102,   103,
      -1,   105,   106,    -1,   108,    -1,   110,    -1,    -1,    -1,
      -1,    -1,   116,    -1,   118,   119,   120,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,   128,   129,   130,   131,   132,   133,
     134,    -1,    -1,   137,   138,    -1,   140,    -1,    -1,    -1,
     144,     3,     4,     5,     6,     7,   150,   151,   152,   153,
      12,    -1,    -1,   157,   158,    -1,    -1,   161,    -1,   163,
     164,    -1,   166,    -1,    26,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    44,    45,    -1,    -1,    -1,    -1,    50,    -1,
      52,    53,    54,    55,    56,    57,    -1,    -1,    60,    61,
      62,    63,    64,    65,    66,    67,    68,    69,    -1,    -1,
      -1,    73,    74,    75,    76,    77,    -1,    79,    -1,    -1,
      -1,    83,    84,    85,    86,    87,    -1,    89,    -1,    91,
      -1,    93,    -1,    -1,    96,    -1,    -1,    -1,   100,   101,
     102,   103,    -1,   105,   106,    -1,   108,    -1,   110,    -1,
      -1,    -1,    -1,    -1,   116,    -1,   118,   119,   120,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,   128,   129,   130,   131,
     132,   133,   134,    -1,    -1,   137,   138,    -1,   140,    -1,
      -1,    -1,   144,     3,     4,     5,     6,     7,   150,   151,
     152,   153,    12,    -1,    -1,   157,   158,    -1,    -1,   161,
      -1,   163,   164,    -1,   166,    -1,    26,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    44,    45,    -1,    -1,    -1,    -1,
      50,    -1,    52,    53,    54,    55,    56,    57,    -1,    -1,
      60,    61,    62,    63,    64,    65,    66,    67,    68,    69,
      -1,    -1,    -1,    73,    74,    75,    76,    77,    -1,    79,
      -1,    -1,    -1,    83,    84,    85,    86,    87,    -1,    89,
      -1,    91,    -1,    93,    -1,    -1,    96,    -1,    -1,    -1,
     100,   101,   102,   103,    -1,   105,   106,    -1,   108,    -1,
     110,    -1,    -1,    -1,    -1,    -1,   116,    -1,   118,   119,
     120,    -1,    -1,    -1,    -1,    -1,    -1,    -1,   128,   129,
     130,   131,   132,   133,   134,    -1,    -1,   137,   138,    -1,
     140,    -1,    -1,    -1,   144,     3,     4,     5,     6,     7,
     150,   151,   152,   153,    12,    -1,    -1,   157,   158,    -1,
      -1,   161,    -1,   163,   164,    -1,   166,    -1,    26,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    44,    45,    -1,    -1,
      -1,    -1,    50,    -1,    52,    53,    54,    55,    56,    57,
      -1,    -1,    60,    61,    62,    63,    64,    65,    66,    67,
      68,    69,    -1,    -1,    -1,    73,    74,    75,    76,    77,
      -1,    79,    -1,    -1,    -1,    83,    84,    85,    86,    87,
      -1,    89,    -1,    91,    -1,    93,    -1,    -1,    96,    -1,
      -1,    -1,   100,   101,   102,   103,    -1,   105,   106,    -1,
     108,    -1,   110,    -1,    -1,    -1,    -1,    -1,   116,    -1,
     118,   119,   120,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
     128,   129,   130,   131,   132,   133,   134,    -1,    -1,   137,
     138,    -1,   140,    -1,    -1,    -1,   144,     3,     4,     5,
       6,     7,   150,   151,   152,   153,    12,    -1,    -1,   157,
     158,    -1,    -1,   161,    -1,   163,   164,    -1,   166,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    44,    45,
      -1,    -1,    -1,    -1,    50,    -1,    52,    53,    54,    55,
      56,    57,    -1,    -1,    60,    61,    62,    63,    64,    65,
      66,    67,    68,    69,    -1,    -1,    -1,    73,    74,    75,
      76,    77,    -1,    79,    -1,    -1,    -1,    83,    84,    85,
      86,    87,    -1,    89,    -1,    91,    -1,    93,    -1,    -1,
      96,    -1,    -1,    -1,   100,   101,   102,   103,    -1,   105,
     106,    -1,   108,    -1,   110,    -1,    -1,    -1,    -1,    -1,
     116,    -1,   118,   119,   120,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,   128,   129,   130,   131,   132,   133,   134,    -1,
      -1,   137,   138,    -1,   140,    -1,    -1,    -1,   144,     3,
       4,     5,     6,     7,   150,   151,   152,   153,    12,    -1,
      -1,   157,   158,    -1,    -1,   161,    -1,   163,   164,    -1,
     166,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    32,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      44,    45,    -1,    -1,    -1,    -1,    50,    -1,    52,    53,
      54,    55,    56,    57,    -1,    -1,    60,    61,    62,    63,
      64,    65,    66,    67,    68,    -1,    -1,    -1,    -1,    73,
      74,    75,    76,    77,    -1,    -1,    -1,    -1,    -1,    83,
      84,    32,    33,    34,    35,    36,    37,    38,    39,    40,
      41,    42,    43,    44,    45,    46,    47,    48,    49,   103,
      51,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,   116,    -1,    -1,   119,   120,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,   128,   129,   130,   131,   132,   133,
     134,    -1,     3,     4,     5,     6,     7,    -1,    -1,    -1,
     144,    12,    -1,    -1,    -1,    -1,   150,   151,   152,   153,
      -1,    -1,    -1,   157,   158,    -1,   160,   161,    -1,    -1,
      -1,    32,   166,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    44,    45,    -1,    -1,    -1,    -1,    50,
      -1,    52,    53,    54,    55,    56,    57,    -1,    -1,    60,
      61,    62,    63,    64,    65,    66,    67,    68,    -1,    -1,
      -1,    -1,    73,    74,    75,    76,    77,    -1,    -1,    -1,
      -1,    -1,    83,    84,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,   103,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,   116,    -1,    -1,   119,   120,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,   128,   129,   130,
     131,   132,   133,   134,    -1,     3,     4,     5,     6,     7,
      -1,    -1,    -1,   144,    12,    -1,    -1,    -1,    -1,   150,
     151,   152,   153,    -1,    -1,    -1,   157,   158,    -1,    -1,
     161,    -1,    -1,    -1,    -1,   166,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    44,    45,    -1,    -1,
      -1,    -1,    50,    -1,    52,    53,    54,    55,    56,    57,
      -1,    -1,    60,    61,    62,    63,    64,    65,    66,    67,
      68,    -1,    -1,    -1,    -1,    73,    74,    75,    76,    77,
      -1,    -1,    -1,    -1,    -1,    83,    84,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,   103,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,   116,    -1,
      -1,   119,   120,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
     128,   129,   130,   131,   132,   133,   134,    -1,     3,     4,
       5,     6,     7,    -1,    -1,    -1,   144,    12,    -1,    -1,
      -1,    -1,   150,   151,   152,   153,    -1,    -1,    -1,   157,
     158,    -1,    -1,   161,    -1,   163,    -1,    -1,   166,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    44,
      45,    -1,    -1,    -1,    -1,    50,    -1,    52,    53,    54,
      55,    56,    57,    -1,    -1,    60,    61,    62,    63,    64,
      65,    66,    67,    68,    -1,    -1,    -1,    -1,    73,    74,
      75,    76,    77,    -1,    -1,    -1,    -1,    -1,    83,    84,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,   103,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,   116,    -1,    -1,   119,   120,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,   128,   129,   130,   131,   132,   133,   134,
      -1,     3,     4,     5,     6,     7,    -1,    -1,    -1,   144,
      12,    -1,    -1,    -1,    -1,   150,   151,   152,   153,    -1,
      -1,    -1,   157,   158,    -1,    -1,   161,    -1,   163,    -1,
      -1,   166,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    44,    45,    -1,    -1,    -1,    -1,    50,    -1,
      52,    53,    54,    55,    56,    57,    -1,    -1,    60,    61,
      62,    63,    64,    65,    66,    67,    68,    -1,    -1,    -1,
      -1,    73,    74,    75,    76,    77,    -1,    -1,    -1,    -1,
      -1,    83,    84,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,   103,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,   116,    -1,    -1,   119,   120,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,   128,   129,   130,   131,
     132,   133,   134,    -1,     3,     4,     5,     6,     7,    -1,
      -1,    -1,   144,    12,    -1,    -1,    -1,    -1,   150,   151,
     152,   153,    -1,    -1,    -1,   157,   158,    -1,    -1,   161,
      -1,   163,    -1,    -1,   166,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    44,    45,    -1,    -1,    -1,
      -1,    50,    -1,    52,    53,    54,    55,    56,    57,    -1,
      -1,    60,    61,    62,    63,    64,    65,    66,    67,    68,
      -1,    -1,    -1,    -1,    73,    74,    75,    76,    77,    -1,
      -1,    -1,    -1,    -1,    83,    84,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,   103,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,   116,    -1,    -1,
     119,   120,    -1,    -1,    -1,    -1,    -1,    -1,    -1,   128,
     129,   130,   131,   132,   133,   134,    -1,     3,     4,     5,
       6,     7,    -1,    -1,    -1,   144,    12,    -1,    -1,    -1,
      -1,   150,   151,   152,   153,    -1,    -1,    -1,   157,   158,
      26,    -1,   161,   162,    -1,    -1,    -1,   166,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    44,    45,
      -1,    -1,    -1,    -1,    50,    -1,    52,    53,    54,    55,
      56,    57,    -1,    -1,    60,    61,    62,    63,    64,    65,
      66,    67,    68,    -1,    -1,    -1,    -1,    73,    74,    75,
      76,    77,    -1,    -1,    -1,    -1,    -1,    83,    84,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,   103,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
     116,    -1,    -1,   119,   120,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,   128,   129,   130,   131,   132,   133,   134,    -1,
       3,     4,     5,     6,     7,    -1,    -1,    -1,   144,    12,
      -1,    -1,    -1,    -1,   150,   151,   152,   153,    -1,    -1,
      -1,   157,   158,    -1,    -1,   161,    -1,    -1,    -1,    32,
     166,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    44,    45,    -1,    -1,    -1,    -1,    50,    -1,    52,
      53,    54,    55,    56,    57,    -1,    -1,    60,    61,    62,
      63,    64,    65,    66,    67,    68,    -1,    -1,    -1,    -1,
      73,    74,    75,    76,    77,    -1,    -1,    -1,    -1,    -1,
      83,    84,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
     103,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,   116,    -1,    -1,   119,   120,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,   128,   129,   130,   131,   132,
     133,   134,    -1,     3,     4,     5,     6,     7,    -1,    -1,
      -1,   144,    12,    -1,    -1,    -1,    -1,   150,   151,   152,
     153,    -1,    -1,    -1,   157,   158,    -1,    -1,   161,    -1,
      -1,    -1,    32,   166,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    44,    45,    -1,    -1,    -1,    -1,
      50,    -1,    52,    53,    54,    55,    56,    57,    -1,    -1,
      60,    61,    62,    63,    64,    65,    66,    67,    68,    -1,
      -1,    -1,    -1,    73,    74,    75,    76,    77,    -1,    -1,
      -1,    -1,    -1,    83,    84,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,   103,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,   116,    -1,    -1,   119,
     120,    -1,    -1,    -1,    -1,    -1,    -1,    -1,   128,   129,
     130,   131,   132,   133,   134,    -1,     3,     4,     5,     6,
       7,    -1,    -1,    -1,   144,    12,    -1,    -1,    -1,    -1,
     150,   151,   152,   153,    -1,    -1,    -1,   157,   158,    -1,
      -1,   161,    -1,    -1,    -1,    32,   166,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    44,    45,    -1,
      -1,    -1,    -1,    50,    -1,    52,    53,    54,    55,    56,
      57,    -1,    -1,    60,    61,    62,    63,    64,    65,    66,
      67,    68,    -1,    -1,    -1,    -1,    73,    74,    75,    76,
      77,    -1,    -1,    -1,    -1,    -1,    83,    84,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,   103,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,   116,
      -1,    -1,   119,   120,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,   128,   129,   130,   131,   132,   133,   134,    -1,     3,
       4,     5,     6,     7,    -1,    -1,    -1,   144,    12,    -1,
      -1,    -1,    -1,   150,   151,   152,   153,    -1,    -1,    -1,
     157,   158,    -1,    -1,   161,    -1,    -1,    -1,    32,   166,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      44,    45,    -1,    -1,    -1,    -1,    50,    -1,    52,    53,
      54,    55,    56,    57,    -1,    -1,    60,    61,    62,    63,
      64,    65,    66,    67,    68,    -1,    -1,    -1,    -1,    73,
      74,    75,    76,    77,    -1,    -1,    -1,    -1,    -1,    83,
      84,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,   103,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,   116,    -1,    -1,   119,   120,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,   128,   129,   130,   131,   132,   133,
     134,    -1,     3,     4,     5,     6,     7,    -1,    -1,    -1,
     144,    12,    -1,    -1,    -1,    -1,   150,   151,   152,   153,
      -1,    -1,    -1,   157,   158,    -1,    -1,   161,    -1,    -1,
      -1,    -1,   166,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    44,    45,    -1,    -1,    -1,    -1,    50,
      -1,    52,    53,    54,    55,    56,    57,    -1,    -1,    60,
      61,    62,    63,    64,    65,    66,    67,    68,    -1,    -1,
      -1,    -1,    73,    74,    75,    76,    77,    -1,    -1,    -1,
      -1,    -1,    83,    84,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,   103,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,   116,    -1,    -1,   119,   120,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,   128,   129,   130,
     131,   132,   133,   134,    -1,     3,     4,     5,     6,     7,
      -1,    -1,    -1,   144,    12,    -1,    -1,    -1,    -1,   150,
     151,   152,   153,    -1,    -1,    -1,   157,   158,    -1,    -1,
     161,    -1,    -1,    -1,    -1,   166,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    44,    45,    -1,    -1,
      -1,    -1,    50,    -1,    52,    53,    54,    55,    56,    57,
      -1,    -1,    60,    61,    62,    63,    64,    65,    66,    67,
      68,    -1,    -1,    -1,    -1,    73,    74,    75,    76,    77,
      -1,    -1,    -1,    -1,    -1,    83,    84,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,   103,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,   116,    -1,
      -1,   119,   120,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
     128,   129,   130,   131,   132,   133,   134,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,   144,    -1,    -1,    -1,
      -1,    -1,   150,   151,   152,   153,     9,    10,    11,   157,
     158,    -1,    -1,   161,    -1,    -1,    -1,    -1,   166,    -1,
      -1,    -1,    25,    -1,    27,    28,    29,    30,    31,    32,
      33,    34,    35,    36,    37,    38,    39,    40,    41,    42,
      43,    44,    45,    46,    47,    48,    49,    -1,    51,     9,
      10,    11,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    25,    -1,    27,    28,    29,
      30,    31,    32,    33,    34,    35,    36,    37,    38,    39,
      40,    41,    42,    43,    44,    45,    46,    47,    48,    49,
      -1,    51,     9,    10,    11,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    25,    -1,
      27,    28,    29,    30,    31,    32,    33,    34,    35,    36,
      37,    38,    39,    40,    41,    42,    43,    44,    45,    46,
      47,    48,    49,    -1,    51,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,     9,    10,    11,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    25,   165,    27,    28,    29,    30,    31,    32,    33,
      34,    35,    36,    37,    38,    39,    40,    41,    42,    43,
      44,    45,    46,    47,    48,    49,    -1,    51,    -1,    -1,
       9,    10,    11,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,   165,    25,    26,    27,    28,
      29,    30,    31,    32,    33,    34,    35,    36,    37,    38,
      39,    40,    41,    42,    43,    44,    45,    46,    47,    48,
      49,    -1,    51,     9,    10,    11,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,   165,    25,
      -1,    27,    28,    29,    30,    31,    32,    33,    34,    35,
      36,    37,    38,    39,    40,    41,    42,    43,    44,    45,
      46,    47,    48,    49,    -1,    51,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,     9,    10,    11,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      25,   165,    27,    28,    29,    30,    31,    32,    33,    34,
      35,    36,    37,    38,    39,    40,    41,    42,    43,    44,
      45,    46,    47,    48,    49,    -1,    51,     9,    10,    11,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    25,   163,    27,    28,    29,    30,    31,
      32,    33,    34,    35,    36,    37,    38,    39,    40,    41,
      42,    43,    44,    45,    46,    47,    48,    49,    -1,    51,
       9,    10,    11,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    25,   163,    27,    28,
      29,    30,    31,    32,    33,    34,    35,    36,    37,    38,
      39,    40,    41,    42,    43,    44,    45,    46,    47,    48,
      49,    -1,    51,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,     9,    10,    11,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    25,   163,    27,
      28,    29,    30,    31,    32,    33,    34,    35,    36,    37,
      38,    39,    40,    41,    42,    43,    44,    45,    46,    47,
      48,    49,    -1,    51,     9,    10,    11,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      25,   163,    27,    28,    29,    30,    31,    32,    33,    34,
      35,    36,    37,    38,    39,    40,    41,    42,    43,    44,
      45,    46,    47,    48,    49,    -1,    51,     9,    10,    11,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    25,   163,    27,    28,    29,    30,    31,
      32,    33,    34,    35,    36,    37,    38,    39,    40,    41,
      42,    43,    44,    45,    46,    47,    48,    49,    -1,    51,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,     9,    10,    11,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    25,   162,    27,    28,    29,    30,    31,
      32,    33,    34,    35,    36,    37,    38,    39,    40,    41,
      42,    43,    44,    45,    46,    47,    48,    49,    -1,    51,
       9,    10,    11,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    25,   162,    27,    28,
      29,    30,    31,    32,    33,    34,    35,    36,    37,    38,
      39,    40,    41,    42,    43,    44,    45,    46,    47,    48,
      49,    -1,    51,     9,    10,    11,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    25,
     162,    27,    28,    29,    30,    31,    32,    33,    34,    35,
      36,    37,    38,    39,    40,    41,    42,    43,    44,    45,
      46,    47,    48,    49,    -1,    51,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,     9,    10,    11,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    25,
     162,    27,    28,    29,    30,    31,    32,    33,    34,    35,
      36,    37,    38,    39,    40,    41,    42,    43,    44,    45,
      46,    47,    48,    49,    -1,    51,     9,    10,    11,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    25,   162,    27,    28,    29,    30,    31,    32,
      33,    34,    35,    36,    37,    38,    39,    40,    41,    42,
      43,    44,    45,    46,    47,    48,    49,    -1,    51,     9,
      10,    11,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    25,   162,    27,    28,    29,
      30,    31,    32,    33,    34,    35,    36,    37,    38,    39,
      40,    41,    42,    43,    44,    45,    46,    47,    48,    49,
      -1,    51,    -1,    -1,    -1,    -1,    -1,    -1,    -1,     9,
      10,    11,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    25,   162,    27,    28,    29,
      30,    31,    32,    33,    34,    35,    36,    37,    38,    39,
      40,    41,    42,    43,    44,    45,    46,    47,    48,    49,
      -1,    51,     9,    10,    11,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    25,   162,
      27,    28,    29,    30,    31,    32,    33,    34,    35,    36,
      37,    38,    39,    40,    41,    42,    43,    44,    45,    46,
      47,    48,    49,    -1,    51,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,    -1,   162,     9,    10,    11,    -1,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,   127,    -1,    25,
      -1,    27,    28,    29,    30,    31,    32,    33,    34,    35,
      36,    37,    38,    39,    40,    41,    42,    43,    44,    45,
      46,    47,    48,    49,    -1,    51,    44,    45,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
     127,    -1,    -1,    -1,    -1,    -1,    -1,    65,    -1,    -1,
       9,    10,    11,    -1,    -1,    73,    74,    75,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    83,    25,    26,    27,    28,
      29,    30,    31,    32,    33,    34,    35,    36,    37,    38,
      39,    40,    41,    42,    43,    44,    45,    46,    47,    48,
      49,    -1,    51,    -1,    -1,    -1,    -1,    -1,   116,    -1,
      -1,   127,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      -1,   129,   130,   131,   132,   133,   134,    -1,    -1,    -1,
      -1,    -1,     9,    10,    11,    -1,   144,    -1,    -1,    -1,
      -1,    -1,   150,   151,   152,   153,    -1,    -1,    25,   157,
      27,    28,    29,    30,    31,    32,    33,    34,    35,    36,
      37,    38,    39,    40,    41,    42,    43,    44,    45,    46,
      47,    48,    49,    -1,    51,    10,    11,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,
      25,    -1,    27,    28,    29,    30,    31,    32,    33,    34,
      35,    36,    37,    38,    39,    40,    41,    42,    43,    44,
      45,    46,    47,    48,    49,    11,    51,    -1,    -1,    -1,
      -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    -1,    25,
      -1,    27,    28,    29,    30,    31,    32,    33,    34,    35,
      36,    37,    38,    39,    40,    41,    42,    43,    44,    45,
      46,    47,    48,    49,    -1,    51,    27,    28,    29,    30,
      31,    32,    33,    34,    35,    36,    37,    38,    39,    40,
      41,    42,    43,    44,    45,    46,    47,    48,    49,    -1,
      51,    29,    30,    31,    32,    33,    34,    35,    36,    37,
      38,    39,    40,    41,    42,    43,    44,    45,    46,    47,
      48,    49,    -1,    51,    30,    31,    32,    33,    34,    35,
      36,    37,    38,    39,    40,    41,    42,    43,    44,    45,
      46,    47,    48,    49,    -1,    51,    31,    32,    33,    34,
      35,    36,    37,    38,    39,    40,    41,    42,    43,    44,
      45,    46,    47,    48,    49,    -1,    51
};

/* YYSTOS[STATE-NUM] -- The (internal number of the) accessing
   symbol of state STATE-NUM.  */
static const yytype_uint16 yystos[] =
{
       0,   169,   170,     0,     3,     4,     5,     6,     7,    12,
      44,    45,    50,    52,    53,    54,    55,    56,    57,    60,
      61,    62,    63,    64,    65,    66,    67,    68,    69,    73,
      74,    75,    76,    77,    79,    83,    84,    85,    86,    87,
      89,    91,    93,    96,   100,   101,   102,   103,   104,   105,
     106,   108,   109,   110,   114,   115,   116,   118,   119,   120,
     121,   122,   123,   128,   129,   130,   131,   132,   133,   134,
     137,   138,   140,   144,   150,   151,   152,   153,   156,   157,
     158,   161,   163,   164,   166,   171,   172,   175,   178,   179,
     186,   187,   189,   190,   191,   193,   248,   249,   250,   253,
     254,   262,   265,   269,   270,   272,   273,   279,   280,   281,
     282,   283,   284,   285,   286,   291,   296,   298,   299,   300,
     301,   302,    75,   116,   150,   158,   250,   269,   269,   161,
     269,   269,   269,   269,   269,   269,   269,   269,   269,   269,
     269,   269,   269,   269,   269,    75,   116,   150,   153,   161,
     171,   254,   272,   273,   281,   272,    32,   269,   294,   295,
     269,   122,   150,   153,   171,   254,   256,   257,   281,   284,
     285,   291,   161,   260,   161,    26,    65,    65,   245,   269,
     178,   161,   161,   161,   161,   161,   163,   269,   163,   269,
      75,    75,   163,   248,   269,   273,   164,   269,   153,   171,
     173,   174,    77,   166,   220,   221,   122,   122,    77,   222,
     250,   161,   161,   161,   161,   161,   161,   153,   164,   171,
     171,   248,   269,   273,   249,   269,   301,   176,   164,   153,
     161,     8,   163,    75,    75,   163,    32,   188,    65,   147,
       9,    10,    11,    25,    27,    28,    29,    30,    31,    32,
      33,    34,    35,    36,    37,    38,    39,    40,    41,    42,
      43,    44,    45,    46,    47,    48,    49,    51,   163,    63,
      64,    13,    14,    15,    16,    17,    18,    19,    20,    21,
      22,    23,    24,   161,   147,    65,   126,    65,   164,   166,
     285,    65,    65,    65,   188,   269,   153,   171,   301,   147,
     147,   271,   273,   127,   167,     8,   267,   161,   261,   153,
     171,   147,   261,   147,   126,   285,   162,   269,   269,   269,
     287,   287,     8,   163,    87,   269,   246,   247,   269,   248,
     273,    75,   202,   269,   163,   163,   163,    13,   163,   163,
     176,   163,   171,    95,     8,   163,   164,   270,   273,     8,
     163,    13,     8,   163,   188,   184,   185,   273,   273,   297,
     273,   162,   128,   273,   292,   293,   294,   171,   170,   163,
     164,   161,   127,   162,   162,   162,   121,   165,   177,   178,
     186,   187,   269,    75,    32,   160,   217,   218,   219,   269,
      75,   124,   192,   124,   194,    75,   161,   287,    75,   279,
     285,   291,   269,   269,   269,    26,   269,   269,   269,   269,
     269,   269,   269,   269,   269,   269,   269,   269,   269,   269,
     269,   269,   269,   269,   269,   269,   269,   269,   269,   269,
     256,    32,   269,   269,   269,   269,   269,   269,   269,   269,
     269,   269,   269,   269,   217,    75,   279,   287,    75,   164,
     279,   288,   289,   290,   287,   269,   287,   287,   287,   162,
     171,    75,    75,    32,   269,    32,   269,   217,   192,   171,
     279,   279,   288,   162,   162,   167,   167,   269,   161,   162,
     163,     8,    95,    95,    13,     8,   162,   162,    44,    45,
      65,    83,   129,   150,   153,   171,   254,   262,   263,   264,
     165,    95,    75,   174,   269,   221,   263,    77,   161,     8,
     162,     8,   162,   162,   163,   161,     8,   162,   162,   161,
     165,   170,   217,   248,   273,   161,   165,   271,   269,   162,
       8,    13,   150,   153,   171,   255,   125,   195,   196,   255,
     164,   161,    25,   129,   155,   211,   212,   214,   215,   255,
     167,   161,   161,   285,   269,    26,    67,   273,   162,   161,
     161,   167,   269,   161,   276,   277,   278,    65,   164,   167,
     165,   167,   167,   167,   271,   271,   127,   162,   195,   258,
      26,   178,   269,    26,   178,   206,   246,   269,   273,    32,
     198,   273,   263,    75,    26,   178,   201,    26,   164,   203,
     263,   263,   263,   266,   268,   161,   153,   171,   147,   107,
     159,   180,   181,   183,    75,   165,    13,   211,   185,   163,
     273,   292,   293,    13,   217,   165,   162,   162,   219,   263,
     153,   171,   196,   164,     8,   223,   211,   215,   162,     8,
      32,    77,   160,   213,   217,   217,   269,   256,   217,   217,
     165,   217,    65,    65,   274,   287,   269,    32,   269,   164,
     126,   259,   176,   207,   162,   176,   163,   127,   197,   273,
     197,    13,   176,   163,   204,   163,   204,   127,   167,     8,
     267,   266,   171,    75,   161,   164,   181,   182,   183,   263,
     162,   162,   269,   162,   163,   171,   223,   255,   104,   109,
     111,   112,   113,   114,   115,   116,   117,   165,   224,   226,
     239,   240,   241,   242,   244,   162,   109,   251,   214,   213,
      77,    13,   162,   162,   261,   162,   162,   162,   287,   287,
     126,   275,   167,   165,   271,   223,   288,   208,    70,    71,
     209,   163,    88,   246,   198,   162,   162,   263,    94,   204,
      97,    98,    99,   204,   165,   263,   263,   162,   255,   176,
     251,   165,    75,   227,   255,    77,   243,   250,   242,     8,
     163,    26,   216,   161,   216,    32,   213,    13,   263,   167,
     167,   288,   165,    70,    71,   210,   161,   178,   163,   162,
      26,   178,   200,   200,   163,    97,   163,   269,    26,   163,
     205,   165,   127,    77,   165,   216,    13,     8,   163,   164,
     228,    13,     8,   163,   225,    75,   214,   164,    32,    77,
     252,   164,   213,    13,   263,   278,   161,    26,    72,   269,
      26,   178,   199,   176,   163,   205,   176,   263,   162,   164,
     263,   255,    75,   229,   230,   231,   232,   234,   235,   236,
     255,   263,    77,   188,    13,   176,    77,     8,   162,   176,
      13,   263,   269,   176,   163,   162,   176,    92,   176,   164,
     176,   165,   231,   163,    95,   154,   163,   147,    13,    75,
     263,   165,    32,    77,   165,   263,   162,   178,    90,   163,
     176,   165,   237,   242,   233,   255,    75,   263,   161,    77,
      26,   163,   165,    75,     8,   211,   176,   255,   162,   216,
     163,   164,   238,   176,   165
};

#define yyerrok		(yyerrstatus = 0)
#define yyclearin	(yychar = YYEMPTY)
#define YYEMPTY		(-2)
#define YYEOF		0

#define YYACCEPT	goto yyacceptlab
#define YYABORT		goto yyabortlab
#define YYERROR		goto yyerrorlab


/* Like YYERROR except do call yyerror.  This remains here temporarily
   to ease the transition to the new meaning of YYERROR, for GCC.
   Once GCC version 2 has supplanted version 1, this can go.  */

#define YYFAIL		goto yyerrlab

#define YYRECOVERING()  (!!yyerrstatus)

#define YYBACKUP(Token, Value)					\
do								\
  if (yychar == YYEMPTY && yylen == 1)				\
    {								\
      yychar = (Token);						\
      yylval = (Value);						\
      yytoken = YYTRANSLATE (yychar);				\
      YYPOPSTACK (1);						\
      goto yybackup;						\
    }								\
  else								\
    {								\
      yyerror (yyscanner, root, YY_("syntax error: cannot back up")); \
      YYERROR;							\
    }								\
while (YYID (0))


#define YYTERROR	1
#define YYERRCODE	256


/* YYLLOC_DEFAULT -- Set CURRENT to span from RHS[1] to RHS[N].
   If N is 0, then set CURRENT to the empty location which ends
   the previous symbol: RHS[0] (always defined).  */

#define YYRHSLOC(Rhs, K) ((Rhs)[K])
#ifndef YYLLOC_DEFAULT
# define YYLLOC_DEFAULT(Current, Rhs, N)				\
    do									\
      if (YYID (N))                                                    \
	{								\
	  (Current).first_line   = YYRHSLOC (Rhs, 1).first_line;	\
	  (Current).first_column = YYRHSLOC (Rhs, 1).first_column;	\
	  (Current).last_line    = YYRHSLOC (Rhs, N).last_line;		\
	  (Current).last_column  = YYRHSLOC (Rhs, N).last_column;	\
	}								\
      else								\
	{								\
	  (Current).first_line   = (Current).last_line   =		\
	    YYRHSLOC (Rhs, 0).last_line;				\
	  (Current).first_column = (Current).last_column =		\
	    YYRHSLOC (Rhs, 0).last_column;				\
	}								\
    while (YYID (0))
#endif


/* YY_LOCATION_PRINT -- Print the location on the stream.
   This macro was not mandated originally: define only if we know
   we won't break user code: when these are the locations we know.  */

#ifndef YY_LOCATION_PRINT
# if defined YYLTYPE_IS_TRIVIAL && YYLTYPE_IS_TRIVIAL
#  define YY_LOCATION_PRINT(File, Loc)			\
     fprintf (File, "%d.%d-%d.%d",			\
	      (Loc).first_line, (Loc).first_column,	\
	      (Loc).last_line,  (Loc).last_column)
# else
#  define YY_LOCATION_PRINT(File, Loc) ((void) 0)
# endif
#endif


/* YYLEX -- calling `yylex' with the right arguments.  */

#ifdef YYLEX_PARAM
# define YYLEX yylex (&yylval, YYLEX_PARAM)
#else
# define YYLEX yylex (&yylval, yyscanner)
#endif

/* Enable debugging if requested.  */
#if YYDEBUG

# ifndef YYFPRINTF
#  include <stdio.h> /* INFRINGES ON USER NAME SPACE */
#  define YYFPRINTF fprintf
# endif

# define YYDPRINTF(Args)			\
do {						\
  if (yydebug)					\
    YYFPRINTF Args;				\
} while (YYID (0))

# define YY_SYMBOL_PRINT(Title, Type, Value, Location)			  \
do {									  \
  if (yydebug)								  \
    {									  \
      YYFPRINTF (stderr, "%s ", Title);					  \
      yy_symbol_print (stderr,						  \
		  Type, Value, yyscanner, root); \
      YYFPRINTF (stderr, "\n");						  \
    }									  \
} while (YYID (0))


/*--------------------------------.
| Print this symbol on YYOUTPUT.  |
`--------------------------------*/

/*ARGSUSED*/
#if (defined __STDC__ || defined __C99__FUNC__ \
     || defined __cplusplus || defined _MSC_VER)
static void
yy_symbol_value_print (FILE *yyoutput, int yytype, YYSTYPE const * const yyvaluep, void* yyscanner, xhpast::Node** root)
#else
static void
yy_symbol_value_print (yyoutput, yytype, yyvaluep, yyscanner, root)
    FILE *yyoutput;
    int yytype;
    YYSTYPE const * const yyvaluep;
    void* yyscanner;
    xhpast::Node** root;
#endif
{
  if (!yyvaluep)
    return;
  YYUSE (yyscanner);
  YYUSE (root);
# ifdef YYPRINT
  if (yytype < YYNTOKENS)
    YYPRINT (yyoutput, yytoknum[yytype], *yyvaluep);
# else
  YYUSE (yyoutput);
# endif
  switch (yytype)
    {
      default:
	break;
    }
}


/*--------------------------------.
| Print this symbol on YYOUTPUT.  |
`--------------------------------*/

#if (defined __STDC__ || defined __C99__FUNC__ \
     || defined __cplusplus || defined _MSC_VER)
static void
yy_symbol_print (FILE *yyoutput, int yytype, YYSTYPE const * const yyvaluep, void* yyscanner, xhpast::Node** root)
#else
static void
yy_symbol_print (yyoutput, yytype, yyvaluep, yyscanner, root)
    FILE *yyoutput;
    int yytype;
    YYSTYPE const * const yyvaluep;
    void* yyscanner;
    xhpast::Node** root;
#endif
{
  if (yytype < YYNTOKENS)
    YYFPRINTF (yyoutput, "token %s (", yytname[yytype]);
  else
    YYFPRINTF (yyoutput, "nterm %s (", yytname[yytype]);

  yy_symbol_value_print (yyoutput, yytype, yyvaluep, yyscanner, root);
  YYFPRINTF (yyoutput, ")");
}

/*------------------------------------------------------------------.
| yy_stack_print -- Print the state stack from its BOTTOM up to its |
| TOP (included).                                                   |
`------------------------------------------------------------------*/

#if (defined __STDC__ || defined __C99__FUNC__ \
     || defined __cplusplus || defined _MSC_VER)
static void
yy_stack_print (yytype_int16 *bottom, yytype_int16 *top)
#else
static void
yy_stack_print (bottom, top)
    yytype_int16 *bottom;
    yytype_int16 *top;
#endif
{
  YYFPRINTF (stderr, "Stack now");
  for (; bottom <= top; ++bottom)
    YYFPRINTF (stderr, " %d", *bottom);
  YYFPRINTF (stderr, "\n");
}

# define YY_STACK_PRINT(Bottom, Top)				\
do {								\
  if (yydebug)							\
    yy_stack_print ((Bottom), (Top));				\
} while (YYID (0))


/*------------------------------------------------.
| Report that the YYRULE is going to be reduced.  |
`------------------------------------------------*/

#if (defined __STDC__ || defined __C99__FUNC__ \
     || defined __cplusplus || defined _MSC_VER)
static void
yy_reduce_print (YYSTYPE *yyvsp, int yyrule, void* yyscanner, xhpast::Node** root)
#else
static void
yy_reduce_print (yyvsp, yyrule, yyscanner, root)
    YYSTYPE *yyvsp;
    int yyrule;
    void* yyscanner;
    xhpast::Node** root;
#endif
{
  int yynrhs = yyr2[yyrule];
  int yyi;
  unsigned long int yylno = yyrline[yyrule];
  YYFPRINTF (stderr, "Reducing stack by rule %d (line %lu):\n",
	     yyrule - 1, yylno);
  /* The symbols being reduced.  */
  for (yyi = 0; yyi < yynrhs; yyi++)
    {
      fprintf (stderr, "   $%d = ", yyi + 1);
      yy_symbol_print (stderr, yyrhs[yyprhs[yyrule] + yyi],
		       &(yyvsp[(yyi + 1) - (yynrhs)])
		       		       , yyscanner, root);
      fprintf (stderr, "\n");
    }
}

# define YY_REDUCE_PRINT(Rule)		\
do {					\
  if (yydebug)				\
    yy_reduce_print (yyvsp, Rule, yyscanner, root); \
} while (YYID (0))

/* Nonzero means print parse trace.  It is left uninitialized so that
   multiple parsers can coexist.  */
int yydebug;
#else /* !YYDEBUG */
# define YYDPRINTF(Args)
# define YY_SYMBOL_PRINT(Title, Type, Value, Location)
# define YY_STACK_PRINT(Bottom, Top)
# define YY_REDUCE_PRINT(Rule)
#endif /* !YYDEBUG */


/* YYINITDEPTH -- initial size of the parser's stacks.  */
#ifndef	YYINITDEPTH
# define YYINITDEPTH 200
#endif

/* YYMAXDEPTH -- maximum size the stacks can grow to (effective only
   if the built-in stack extension method is used).

   Do not make this value too large; the results are undefined if
   YYSTACK_ALLOC_MAXIMUM < YYSTACK_BYTES (YYMAXDEPTH)
   evaluated with infinite-precision integer arithmetic.  */

#ifndef YYMAXDEPTH
# define YYMAXDEPTH 10000
#endif



#if YYERROR_VERBOSE

# ifndef yystrlen
#  if defined __GLIBC__ && defined _STRING_H
#   define yystrlen strlen
#  else
/* Return the length of YYSTR.  */
#if (defined __STDC__ || defined __C99__FUNC__ \
     || defined __cplusplus || defined _MSC_VER)
static YYSIZE_T
yystrlen (const char *yystr)
#else
static YYSIZE_T
yystrlen (yystr)
    const char *yystr;
#endif
{
  YYSIZE_T yylen;
  for (yylen = 0; yystr[yylen]; yylen++)
    continue;
  return yylen;
}
#  endif
# endif

# ifndef yystpcpy
#  if defined __GLIBC__ && defined _STRING_H && defined _GNU_SOURCE
#   define yystpcpy stpcpy
#  else
/* Copy YYSRC to YYDEST, returning the address of the terminating '\0' in
   YYDEST.  */
#if (defined __STDC__ || defined __C99__FUNC__ \
     || defined __cplusplus || defined _MSC_VER)
static char *
yystpcpy (char *yydest, const char *yysrc)
#else
static char *
yystpcpy (yydest, yysrc)
    char *yydest;
    const char *yysrc;
#endif
{
  char *yyd = yydest;
  const char *yys = yysrc;

  while ((*yyd++ = *yys++) != '\0')
    continue;

  return yyd - 1;
}
#  endif
# endif

# ifndef yytnamerr
/* Copy to YYRES the contents of YYSTR after stripping away unnecessary
   quotes and backslashes, so that it's suitable for yyerror.  The
   heuristic is that double-quoting is unnecessary unless the string
   contains an apostrophe, a comma, or backslash (other than
   backslash-backslash).  YYSTR is taken from yytname.  If YYRES is
   null, do not copy; instead, return the length of what the result
   would have been.  */
static YYSIZE_T
yytnamerr (char *yyres, const char *yystr)
{
  if (*yystr == '"')
    {
      YYSIZE_T yyn = 0;
      char const *yyp = yystr;

      for (;;)
	switch (*++yyp)
	  {
	  case '\'':
	  case ',':
	    goto do_not_strip_quotes;

	  case '\\':
	    if (*++yyp != '\\')
	      goto do_not_strip_quotes;
	    /* Fall through.  */
	  default:
	    if (yyres)
	      yyres[yyn] = *yyp;
	    yyn++;
	    break;

	  case '"':
	    if (yyres)
	      yyres[yyn] = '\0';
	    return yyn;
	  }
    do_not_strip_quotes: ;
    }

  if (! yyres)
    return yystrlen (yystr);

  return yystpcpy (yyres, yystr) - yyres;
}
# endif

/* Copy into YYRESULT an error message about the unexpected token
   YYCHAR while in state YYSTATE.  Return the number of bytes copied,
   including the terminating null byte.  If YYRESULT is null, do not
   copy anything; just return the number of bytes that would be
   copied.  As a special case, return 0 if an ordinary "syntax error"
   message will do.  Return YYSIZE_MAXIMUM if overflow occurs during
   size calculation.  */
static YYSIZE_T
yysyntax_error (char *yyresult, int yystate, int yychar)
{
  int yyn = yypact[yystate];

  if (! (YYPACT_NINF < yyn && yyn <= YYLAST))
    return 0;
  else
    {
      int yytype = YYTRANSLATE (yychar);
      YYSIZE_T yysize0 = yytnamerr (0, yytname[yytype]);
      YYSIZE_T yysize = yysize0;
      YYSIZE_T yysize1;
      int yysize_overflow = 0;
      enum { YYERROR_VERBOSE_ARGS_MAXIMUM = 5 };
      char const *yyarg[YYERROR_VERBOSE_ARGS_MAXIMUM];
      int yyx;

# if 0
      /* This is so xgettext sees the translatable formats that are
	 constructed on the fly.  */
      YY_("syntax error, unexpected %s");
      YY_("syntax error, unexpected %s, expecting %s");
      YY_("syntax error, unexpected %s, expecting %s or %s");
      YY_("syntax error, unexpected %s, expecting %s or %s or %s");
      YY_("syntax error, unexpected %s, expecting %s or %s or %s or %s");
# endif
      char *yyfmt;
      char const *yyf;
      static char const yyunexpected[] = "syntax error, unexpected %s";
      static char const yyexpecting[] = ", expecting %s";
      static char const yyor[] = " or %s";
      char yyformat[sizeof yyunexpected
		    + sizeof yyexpecting - 1
		    + ((YYERROR_VERBOSE_ARGS_MAXIMUM - 2)
		       * (sizeof yyor - 1))];
      char const *yyprefix = yyexpecting;

      /* Start YYX at -YYN if negative to avoid negative indexes in
	 YYCHECK.  */
      int yyxbegin = yyn < 0 ? -yyn : 0;

      /* Stay within bounds of both yycheck and yytname.  */
      int yychecklim = YYLAST - yyn + 1;
      int yyxend = yychecklim < YYNTOKENS ? yychecklim : YYNTOKENS;
      int yycount = 1;

      yyarg[0] = yytname[yytype];
      yyfmt = yystpcpy (yyformat, yyunexpected);

      for (yyx = yyxbegin; yyx < yyxend; ++yyx)
	if (yycheck[yyx + yyn] == yyx && yyx != YYTERROR)
	  {
	    if (yycount == YYERROR_VERBOSE_ARGS_MAXIMUM)
	      {
		yycount = 1;
		yysize = yysize0;
		yyformat[sizeof yyunexpected - 1] = '\0';
		break;
	      }
	    yyarg[yycount++] = yytname[yyx];
	    yysize1 = yysize + yytnamerr (0, yytname[yyx]);
	    yysize_overflow |= (yysize1 < yysize);
	    yysize = yysize1;
	    yyfmt = yystpcpy (yyfmt, yyprefix);
	    yyprefix = yyor;
	  }

      yyf = YY_(yyformat);
      yysize1 = yysize + yystrlen (yyf);
      yysize_overflow |= (yysize1 < yysize);
      yysize = yysize1;

      if (yysize_overflow)
	return YYSIZE_MAXIMUM;

      if (yyresult)
	{
	  /* Avoid sprintf, as that infringes on the user's name space.
	     Don't have undefined behavior even if the translation
	     produced a string with the wrong number of "%s"s.  */
	  char *yyp = yyresult;
	  int yyi = 0;
	  while ((*yyp = *yyf) != '\0')
	    {
	      if (*yyp == '%' && yyf[1] == 's' && yyi < yycount)
		{
		  yyp += yytnamerr (yyp, yyarg[yyi++]);
		  yyf += 2;
		}
	      else
		{
		  yyp++;
		  yyf++;
		}
	    }
	}
      return yysize;
    }
}
#endif /* YYERROR_VERBOSE */


/*-----------------------------------------------.
| Release the memory associated to this symbol.  |
`-----------------------------------------------*/

/*ARGSUSED*/
#if (defined __STDC__ || defined __C99__FUNC__ \
     || defined __cplusplus || defined _MSC_VER)
static void
yydestruct (const char *yymsg, int yytype, YYSTYPE *yyvaluep, void* yyscanner, xhpast::Node** root)
#else
static void
yydestruct (yymsg, yytype, yyvaluep, yyscanner, root)
    const char *yymsg;
    int yytype;
    YYSTYPE *yyvaluep;
    void* yyscanner;
    xhpast::Node** root;
#endif
{
  YYUSE (yyvaluep);
  YYUSE (yyscanner);
  YYUSE (root);

  if (!yymsg)
    yymsg = "Deleting";
  YY_SYMBOL_PRINT (yymsg, yytype, yyvaluep, yylocationp);

  switch (yytype)
    {

      default:
	break;
    }
}


/* Prevent warnings from -Wmissing-prototypes.  */

#ifdef YYPARSE_PARAM
#if defined __STDC__ || defined __cplusplus
int yyparse (void *YYPARSE_PARAM);
#else
int yyparse ();
#endif
#else /* ! YYPARSE_PARAM */
#if defined __STDC__ || defined __cplusplus
int yyparse (void* yyscanner, xhpast::Node** root);
#else
int yyparse ();
#endif
#endif /* ! YYPARSE_PARAM */






/*----------.
| yyparse.  |
`----------*/

#ifdef YYPARSE_PARAM
#if (defined __STDC__ || defined __C99__FUNC__ \
     || defined __cplusplus || defined _MSC_VER)
int
yyparse (void *YYPARSE_PARAM)
#else
int
yyparse (YYPARSE_PARAM)
    void *YYPARSE_PARAM;
#endif
#else /* ! YYPARSE_PARAM */
#if (defined __STDC__ || defined __C99__FUNC__ \
     || defined __cplusplus || defined _MSC_VER)
int
yyparse (void* yyscanner, xhpast::Node** root)
#else
int
yyparse (yyscanner, root)
    void* yyscanner;
    xhpast::Node** root;
#endif
#endif
{
  /* The look-ahead symbol.  */
int yychar;

/* The semantic value of the look-ahead symbol.  */
YYSTYPE yylval;

/* Number of syntax errors so far.  */
int yynerrs;

  int yystate;
  int yyn;
  int yyresult;
  /* Number of tokens to shift before error messages enabled.  */
  int yyerrstatus;
  /* Look-ahead token as an internal (translated) token number.  */
  int yytoken = 0;
#if YYERROR_VERBOSE
  /* Buffer for error messages, and its allocated size.  */
  char yymsgbuf[128];
  char *yymsg = yymsgbuf;
  YYSIZE_T yymsg_alloc = sizeof yymsgbuf;
#endif

  /* Three stacks and their tools:
     `yyss': related to states,
     `yyvs': related to semantic values,
     `yyls': related to locations.

     Refer to the stacks thru separate pointers, to allow yyoverflow
     to reallocate them elsewhere.  */

  /* The state stack.  */
  yytype_int16 yyssa[YYINITDEPTH];
  yytype_int16 *yyss = yyssa;
  yytype_int16 *yyssp;

  /* The semantic value stack.  */
  YYSTYPE yyvsa[YYINITDEPTH];
  YYSTYPE *yyvs = yyvsa;
  YYSTYPE *yyvsp;



#define YYPOPSTACK(N)   (yyvsp -= (N), yyssp -= (N))

  YYSIZE_T yystacksize = YYINITDEPTH;

  /* The variables used to return semantic value and location from the
     action routines.  */
  YYSTYPE yyval;


  /* The number of symbols on the RHS of the reduced rule.
     Keep to zero when no symbol should be popped.  */
  int yylen = 0;

  YYDPRINTF ((stderr, "Starting parse\n"));

  yystate = 0;
  yyerrstatus = 0;
  yynerrs = 0;
  yychar = YYEMPTY;		/* Cause a token to be read.  */

  /* Initialize stack pointers.
     Waste one element of value and location stack
     so that they stay on the same level as the state stack.
     The wasted elements are never initialized.  */

  yyssp = yyss;
  yyvsp = yyvs;

  goto yysetstate;

/*------------------------------------------------------------.
| yynewstate -- Push a new state, which is found in yystate.  |
`------------------------------------------------------------*/
 yynewstate:
  /* In all cases, when you get here, the value and location stacks
     have just been pushed.  So pushing a state here evens the stacks.  */
  yyssp++;

 yysetstate:
  *yyssp = yystate;

  if (yyss + yystacksize - 1 <= yyssp)
    {
      /* Get the current used size of the three stacks, in elements.  */
      YYSIZE_T yysize = yyssp - yyss + 1;

#ifdef yyoverflow
      {
	/* Give user a chance to reallocate the stack.  Use copies of
	   these so that the &'s don't force the real ones into
	   memory.  */
	YYSTYPE *yyvs1 = yyvs;
	yytype_int16 *yyss1 = yyss;


	/* Each stack pointer address is followed by the size of the
	   data in use in that stack, in bytes.  This used to be a
	   conditional around just the two extra args, but that might
	   be undefined if yyoverflow is a macro.  */
	yyoverflow (YY_("memory exhausted"),
		    &yyss1, yysize * sizeof (*yyssp),
		    &yyvs1, yysize * sizeof (*yyvsp),

		    &yystacksize);

	yyss = yyss1;
	yyvs = yyvs1;
      }
#else /* no yyoverflow */
# ifndef YYSTACK_RELOCATE
      goto yyexhaustedlab;
# else
      /* Extend the stack our own way.  */
      if (YYMAXDEPTH <= yystacksize)
	goto yyexhaustedlab;
      yystacksize *= 2;
      if (YYMAXDEPTH < yystacksize)
	yystacksize = YYMAXDEPTH;

      {
	yytype_int16 *yyss1 = yyss;
	union yyalloc *yyptr =
	  (union yyalloc *) YYSTACK_ALLOC (YYSTACK_BYTES (yystacksize));
	if (! yyptr)
	  goto yyexhaustedlab;
	YYSTACK_RELOCATE (yyss);
	YYSTACK_RELOCATE (yyvs);

#  undef YYSTACK_RELOCATE
	if (yyss1 != yyssa)
	  YYSTACK_FREE (yyss1);
      }
# endif
#endif /* no yyoverflow */

      yyssp = yyss + yysize - 1;
      yyvsp = yyvs + yysize - 1;


      YYDPRINTF ((stderr, "Stack size increased to %lu\n",
		  (unsigned long int) yystacksize));

      if (yyss + yystacksize - 1 <= yyssp)
	YYABORT;
    }

  YYDPRINTF ((stderr, "Entering state %d\n", yystate));

  goto yybackup;

/*-----------.
| yybackup.  |
`-----------*/
yybackup:

  /* Do appropriate processing given the current state.  Read a
     look-ahead token if we need one and don't already have one.  */

  /* First try to decide what to do without reference to look-ahead token.  */
  yyn = yypact[yystate];
  if (yyn == YYPACT_NINF)
    goto yydefault;

  /* Not known => get a look-ahead token if don't already have one.  */

  /* YYCHAR is either YYEMPTY or YYEOF or a valid look-ahead symbol.  */
  if (yychar == YYEMPTY)
    {
      YYDPRINTF ((stderr, "Reading a token: "));
      yychar = YYLEX;
    }

  if (yychar <= YYEOF)
    {
      yychar = yytoken = YYEOF;
      YYDPRINTF ((stderr, "Now at end of input.\n"));
    }
  else
    {
      yytoken = YYTRANSLATE (yychar);
      YY_SYMBOL_PRINT ("Next token is", yytoken, &yylval, &yylloc);
    }

  /* If the proper action on seeing token YYTOKEN is to reduce or to
     detect an error, take that action.  */
  yyn += yytoken;
  if (yyn < 0 || YYLAST < yyn || yycheck[yyn] != yytoken)
    goto yydefault;
  yyn = yytable[yyn];
  if (yyn <= 0)
    {
      if (yyn == 0 || yyn == YYTABLE_NINF)
	goto yyerrlab;
      yyn = -yyn;
      goto yyreduce;
    }

  if (yyn == YYFINAL)
    YYACCEPT;

  /* Count tokens shifted since error; after three, turn off error
     status.  */
  if (yyerrstatus)
    yyerrstatus--;

  /* Shift the look-ahead token.  */
  YY_SYMBOL_PRINT ("Shifting", yytoken, &yylval, &yylloc);

  /* Discard the shifted token unless it is eof.  */
  if (yychar != YYEOF)
    yychar = YYEMPTY;

  yystate = yyn;
  *++yyvsp = yylval;

  goto yynewstate;


/*-----------------------------------------------------------.
| yydefault -- do the default action for the current state.  |
`-----------------------------------------------------------*/
yydefault:
  yyn = yydefact[yystate];
  if (yyn == 0)
    goto yyerrlab;
  goto yyreduce;


/*-----------------------------.
| yyreduce -- Do a reduction.  |
`-----------------------------*/
yyreduce:
  /* yyn is the number of a rule to reduce with.  */
  yylen = yyr2[yyn];

  /* If YYLEN is nonzero, implement the default value of the action:
     `$$ = $1'.

     Otherwise, the following line sets YYVAL to garbage.
     This behavior is undocumented and Bison
     users should not rely upon it.  Assigning to YYVAL
     unconditionally makes the parser a bit smaller, and it avoids a
     GCC warning that YYVAL may be used uninitialized.  */
  yyval = yyvsp[1-yylen];


  YY_REDUCE_PRINT (yyn);
  switch (yyn)
    {
        case 2:
#line 207 "parser.y"
    {
    *root = NNEW(n_PROGRAM)->appendChild((yyvsp[(1) - (1)]));
  ;}
    break;

  case 3:
#line 213 "parser.y"
    {
    (yyval) = (yyvsp[(1) - (2)])->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 4:
#line 216 "parser.y"
    {
    (yyval) = NNEW(n_STATEMENT_LIST);
  ;}
    break;

  case 5:
#line 222 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_SYMBOL_NAME);
  ;}
    break;

  case 6:
#line 225 "parser.y"
    {
    (yyval) = NMORE((yyvsp[(1) - (3)]), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 10:
#line 234 "parser.y"
    {
    (yyvsp[(1) - (4)]) = NSPAN((yyvsp[(1) - (4)]), n_HALT_COMPILER, (yyvsp[(3) - (4)]));
    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (4)]));
    NMORE((yyval), (yyvsp[(4) - (4)]));
  ;}
    break;

  case 11:
#line 239 "parser.y"
    {
    NSPAN((yyvsp[(1) - (3)]), n_NAMESPACE, (yyvsp[(2) - (3)]));
    (yyvsp[(1) - (3)])->appendChild((yyvsp[(2) - (3)]));
    (yyvsp[(1) - (3)])->appendChild(NNEW(n_EMPTY));
    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (3)]));
    NMORE((yyval), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 12:
#line 246 "parser.y"
    {
  NSPAN((yyvsp[(1) - (5)]), n_NAMESPACE, (yyvsp[(5) - (5)]));
  (yyvsp[(1) - (5)])->appendChild((yyvsp[(2) - (5)]));
  (yyvsp[(1) - (5)])->appendChild(NEXPAND((yyvsp[(3) - (5)]), (yyvsp[(4) - (5)]), (yyvsp[(5) - (5)])));
  (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (5)]));
  ;}
    break;

  case 13:
#line 252 "parser.y"
    {
  NSPAN((yyvsp[(1) - (4)]), n_NAMESPACE, (yyvsp[(4) - (4)]));
  (yyvsp[(1) - (4)])->appendChild(NNEW(n_EMPTY));
  NMORE((yyvsp[(3) - (4)]), (yyvsp[(4) - (4)]));
  NMORE((yyvsp[(3) - (4)]), (yyvsp[(2) - (4)]));
  (yyvsp[(1) - (4)])->appendChild((yyvsp[(3) - (4)]));
  (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (4)]));
  ;}
    break;

  case 14:
#line 260 "parser.y"
    {
  NMORE((yyvsp[(2) - (3)]), (yyvsp[(1) - (3)]));
  (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(2) - (3)]));
  NMORE((yyval), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 15:
#line 265 "parser.y"
    {
  (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (2)]));
  NMORE((yyval), (yyvsp[(2) - (2)]));
  ;}
    break;

  case 16:
#line 272 "parser.y"
    {
    (yyval) = (yyvsp[(1) - (3)])->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 17:
#line 275 "parser.y"
    {
    (yyval) = NNEW(n_USE_LIST);
    (yyval)->appendChild((yyvsp[(1) - (1)]));
  ;}
    break;

  case 18:
#line 282 "parser.y"
    {
    (yyval) = NNEW(n_USE);
    (yyval)->appendChild((yyvsp[(1) - (1)]));
    (yyval)->appendChild(NNEW(n_EMPTY));
  ;}
    break;

  case 19:
#line 287 "parser.y"
    {
    (yyval) = NNEW(n_USE);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    NTYPE((yyvsp[(3) - (3)]), n_STRING);
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 20:
#line 293 "parser.y"
    {
    (yyval) = NNEW(n_USE);
    NMORE((yyvsp[(2) - (2)]), (yyvsp[(1) - (2)]));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
    (yyval)->appendChild(NNEW(n_EMPTY));
  ;}
    break;

  case 21:
#line 299 "parser.y"
    {
    (yyval) = NNEW(n_USE);
    NMORE((yyvsp[(2) - (4)]), (yyvsp[(1) - (4)]));
    (yyval)->appendChild((yyvsp[(2) - (4)]));
    NTYPE((yyvsp[(4) - (4)]), n_STRING);
    (yyval)->appendChild((yyvsp[(4) - (4)]));
  ;}
    break;

  case 22:
#line 309 "parser.y"
    {
    NMORE((yyval), (yyvsp[(5) - (5)]));
    (yyval)->appendChild(
      NNEW(n_CONSTANT_DECLARATION)
        ->appendChild(NTYPE((yyvsp[(3) - (5)]), n_STRING))
        ->appendChild((yyvsp[(5) - (5)])));
  ;}
    break;

  case 23:
#line 316 "parser.y"
    {
    NSPAN((yyval), n_CONSTANT_DECLARATION_LIST, (yyvsp[(4) - (4)]));
    (yyval)->appendChild(
      NNEW(n_CONSTANT_DECLARATION)
        ->appendChild(NTYPE((yyvsp[(2) - (4)]), n_STRING))
        ->appendChild((yyvsp[(4) - (4)])));
  ;}
    break;

  case 24:
#line 326 "parser.y"
    {
    (yyval) = (yyvsp[(1) - (2)])->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 25:
#line 329 "parser.y"
    {
    (yyval) = NNEW(n_STATEMENT_LIST);
  ;}
    break;

  case 29:
#line 338 "parser.y"
    {
  (yyvsp[(1) - (4)]) = NSPAN((yyvsp[(1) - (4)]), n_HALT_COMPILER, (yyvsp[(3) - (4)]));
  (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (4)]));
  NMORE((yyval), (yyvsp[(4) - (4)]));
  ;}
    break;

  case 31:
#line 347 "parser.y"
    {
    NTYPE((yyvsp[(1) - (2)]), n_STRING);
    (yyval) = NNEW(n_LABEL);
    (yyval)->appendChild((yyvsp[(1) - (2)]));
    NMORE((yyval), (yyvsp[(2) - (2)]));
  ;}
    break;

  case 32:
#line 353 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_OPEN_TAG);
  ;}
    break;

  case 33:
#line 356 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_OPEN_TAG);
  ;}
    break;

  case 34:
#line 359 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_CLOSE_TAG);
  ;}
    break;

  case 35:
#line 365 "parser.y"
    {
    (yyval) = NEXPAND((yyvsp[(1) - (3)]), (yyvsp[(2) - (3)]), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 36:
#line 368 "parser.y"
    {
    (yyval) = NNEW(n_CONDITION_LIST);

    (yyvsp[(1) - (7)]) = NTYPE((yyvsp[(1) - (7)]), n_IF);
    (yyvsp[(1) - (7)])->appendChild(NSPAN((yyvsp[(2) - (7)]), n_CONTROL_CONDITION, (yyvsp[(4) - (7)]))->appendChild((yyvsp[(3) - (7)])));
    (yyvsp[(1) - (7)])->appendChild((yyvsp[(5) - (7)]));

    (yyval)->appendChild((yyvsp[(1) - (7)]));
    (yyval)->appendChildren((yyvsp[(6) - (7)]));

    // Hacks: merge a list of if (x) { } else if (y) { } into a single condition
    // list instead of a condition tree.

    if ((yyvsp[(7) - (7)])->type == n_EMPTY) {
      // Ignore.
    } else if ((yyvsp[(7) - (7)])->type == n_ELSE) {
      xhpast::Node *stype = (yyvsp[(7) - (7)])->firstChild()->firstChild();
      if (stype && stype->type == n_CONDITION_LIST) {
        NTYPE(stype->firstChild(), n_ELSEIF);
        stype->firstChild()->l_tok = (yyvsp[(7) - (7)])->l_tok;
        (yyval)->appendChildren(stype);
      } else {
        (yyval)->appendChild((yyvsp[(7) - (7)]));
      }
    } else {
      (yyval)->appendChild((yyvsp[(7) - (7)]));
    }

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyval));
  ;}
    break;

  case 37:
#line 402 "parser.y"
    {

    (yyval) = NNEW(n_CONDITION_LIST);
    NTYPE((yyvsp[(1) - (10)]), n_IF);
    (yyvsp[(1) - (10)])->appendChild(NSPAN((yyvsp[(2) - (10)]), n_CONTROL_CONDITION, (yyvsp[(4) - (10)]))->appendChild((yyvsp[(3) - (10)])));
    (yyvsp[(1) - (10)])->appendChild((yyvsp[(6) - (10)]));

    (yyval)->appendChild((yyvsp[(1) - (10)]));
    (yyval)->appendChildren((yyvsp[(7) - (10)]));
    (yyval)->appendChild((yyvsp[(8) - (10)]));
    NMORE((yyval), (yyvsp[(9) - (10)]));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyval));
    NMORE((yyval), (yyvsp[(10) - (10)]));
  ;}
    break;

  case 38:
#line 417 "parser.y"
    {
    NTYPE((yyvsp[(1) - (5)]), n_WHILE);
    (yyvsp[(1) - (5)])->appendChild(NSPAN((yyvsp[(2) - (5)]), n_CONTROL_CONDITION, (yyvsp[(4) - (5)]))->appendChild((yyvsp[(3) - (5)])));
    (yyvsp[(1) - (5)])->appendChild((yyvsp[(5) - (5)]));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (5)]));
  ;}
    break;

  case 39:
#line 424 "parser.y"
    {
    NTYPE((yyvsp[(1) - (7)]), n_DO_WHILE);
    (yyvsp[(1) - (7)])->appendChild((yyvsp[(2) - (7)]));
    (yyvsp[(1) - (7)])->appendChild(NSPAN((yyvsp[(4) - (7)]), n_CONTROL_CONDITION, (yyvsp[(6) - (7)]))->appendChild((yyvsp[(5) - (7)])));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (7)]));
    NMORE((yyval), (yyvsp[(7) - (7)]));
  ;}
    break;

  case 40:
#line 432 "parser.y"
    {
    NTYPE((yyvsp[(1) - (9)]), n_FOR);

    NSPAN((yyvsp[(2) - (9)]), n_FOR_EXPRESSION, (yyvsp[(8) - (9)]))
      ->appendChild((yyvsp[(3) - (9)]))
      ->appendChild((yyvsp[(5) - (9)]))
      ->appendChild((yyvsp[(7) - (9)]));

    (yyvsp[(1) - (9)])->appendChild((yyvsp[(2) - (9)]));
    (yyvsp[(1) - (9)])->appendChild((yyvsp[(9) - (9)]));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (9)]));
  ;}
    break;

  case 41:
#line 445 "parser.y"
    {
    NTYPE((yyvsp[(1) - (5)]), n_SWITCH);
    (yyvsp[(1) - (5)])->appendChild(NSPAN((yyvsp[(2) - (5)]), n_CONTROL_CONDITION, (yyvsp[(4) - (5)]))->appendChild((yyvsp[(3) - (5)])));
    (yyvsp[(1) - (5)])->appendChild((yyvsp[(5) - (5)]));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (5)]));
  ;}
    break;

  case 42:
#line 452 "parser.y"
    {
    NTYPE((yyvsp[(1) - (2)]), n_BREAK);
    (yyvsp[(1) - (2)])->appendChild(NNEW(n_EMPTY));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (2)]));
    NMORE((yyval), (yyvsp[(2) - (2)]));
  ;}
    break;

  case 43:
#line 459 "parser.y"
    {
    NTYPE((yyvsp[(1) - (3)]), n_BREAK);
    (yyvsp[(1) - (3)])->appendChild((yyvsp[(2) - (3)]));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (3)]));
    NMORE((yyval), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 44:
#line 466 "parser.y"
    {
    NTYPE((yyvsp[(1) - (2)]), n_CONTINUE);
    (yyvsp[(1) - (2)])->appendChild(NNEW(n_EMPTY));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (2)]));
    NMORE((yyval), (yyvsp[(2) - (2)]));
  ;}
    break;

  case 45:
#line 473 "parser.y"
    {
    NTYPE((yyvsp[(1) - (3)]), n_CONTINUE);
    (yyvsp[(1) - (3)])->appendChild((yyvsp[(2) - (3)]));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (3)]));
    NMORE((yyval), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 46:
#line 480 "parser.y"
    {
    NTYPE((yyvsp[(1) - (2)]), n_RETURN);
    (yyvsp[(1) - (2)])->appendChild(NNEW(n_EMPTY));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (2)]));
    NMORE((yyval), (yyvsp[(2) - (2)]));
  ;}
    break;

  case 47:
#line 487 "parser.y"
    {
    NTYPE((yyvsp[(1) - (3)]), n_RETURN);
    (yyvsp[(1) - (3)])->appendChild((yyvsp[(2) - (3)]));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (3)]));
    NMORE((yyval), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 48:
#line 494 "parser.y"
    {
    NTYPE((yyvsp[(1) - (3)]), n_RETURN);
    (yyvsp[(1) - (3)])->appendChild((yyvsp[(2) - (3)]));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (3)]));
    NMORE((yyval), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 49:
#line 501 "parser.y"
    {
    NMORE((yyvsp[(2) - (3)]), (yyvsp[(1) - (3)]));
    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(2) - (3)]));
    NMORE((yyval), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 50:
#line 506 "parser.y"
    {
    NMORE((yyvsp[(2) - (3)]), (yyvsp[(1) - (3)]));
    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(2) - (3)]));
    NMORE((yyval), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 51:
#line 511 "parser.y"
    {
    NMORE((yyvsp[(2) - (3)]), (yyvsp[(1) - (3)]));
    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(2) - (3)]));
    NMORE((yyval), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 52:
#line 516 "parser.y"
    {
    NTYPE((yyvsp[(1) - (1)]), n_INLINE_HTML);
    (yyval) = (yyvsp[(1) - (1)]);
  ;}
    break;

  case 53:
#line 520 "parser.y"
    {
    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (2)]));
    NMORE((yyval), (yyvsp[(2) - (2)]));
  ;}
    break;

  case 54:
#line 524 "parser.y"
    {
    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (2)]));
    NMORE((yyval), (yyvsp[(2) - (2)]));
  ;}
    break;

  case 55:
#line 528 "parser.y"
    {
    NMORE((yyvsp[(3) - (5)]), (yyvsp[(4) - (5)]));
    NMORE((yyvsp[(3) - (5)]), (yyvsp[(1) - (5)]));
    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(3) - (5)]));
    NMORE((yyval), (yyvsp[(5) - (5)]));
  ;}
    break;

  case 56:
#line 535 "parser.y"
    {
    NTYPE((yyvsp[(1) - (8)]), n_FOREACH);
    NSPAN((yyvsp[(2) - (8)]), n_FOREACH_EXPRESSION, (yyvsp[(7) - (8)]));
    (yyvsp[(2) - (8)])->appendChild((yyvsp[(3) - (8)]));
    if ((yyvsp[(6) - (8)])->type == n_EMPTY) {
      (yyvsp[(2) - (8)])->appendChild((yyvsp[(6) - (8)]));
      (yyvsp[(2) - (8)])->appendChild((yyvsp[(5) - (8)]));
    } else {
      (yyvsp[(2) - (8)])->appendChild((yyvsp[(5) - (8)]));
      (yyvsp[(2) - (8)])->appendChild((yyvsp[(6) - (8)]));
    }
    (yyvsp[(1) - (8)])->appendChild((yyvsp[(2) - (8)]));

    (yyvsp[(1) - (8)])->appendChild((yyvsp[(8) - (8)]));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (8)]));
  ;}
    break;

  case 57:
#line 553 "parser.y"
    {
    NTYPE((yyvsp[(1) - (8)]), n_FOREACH);
    NSPAN((yyvsp[(2) - (8)]), n_FOREACH_EXPRESSION, (yyvsp[(7) - (8)]));
    (yyvsp[(2) - (8)])->appendChild((yyvsp[(3) - (8)]));
    if ((yyvsp[(6) - (8)])->type == n_EMPTY) {
      (yyvsp[(2) - (8)])->appendChild((yyvsp[(6) - (8)]));
      (yyvsp[(2) - (8)])->appendChild((yyvsp[(5) - (8)]));
    } else {
      (yyvsp[(2) - (8)])->appendChild((yyvsp[(5) - (8)]));
      (yyvsp[(2) - (8)])->appendChild((yyvsp[(6) - (8)]));
    }
    (yyvsp[(1) - (8)])->appendChild((yyvsp[(2) - (8)]));
    (yyvsp[(1) - (8)])->appendChild((yyvsp[(8) - (8)]));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (8)]));
  ;}
    break;

  case 58:
#line 569 "parser.y"
    {
    NTYPE((yyvsp[(1) - (5)]), n_DECLARE);
    (yyvsp[(1) - (5)])->appendChild((yyvsp[(3) - (5)]));
    (yyvsp[(1) - (5)])->appendChild((yyvsp[(5) - (5)]));
    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (5)]));
  ;}
    break;

  case 59:
#line 575 "parser.y"
    {
    (yyval) = NNEW(n_STATEMENT)->appendChild(NNEW(n_EMPTY));
    NMORE((yyval), (yyvsp[(1) - (1)]));
  ;}
    break;

  case 60:
#line 579 "parser.y"
    {
    NTYPE((yyvsp[(1) - (6)]), n_TRY);
    (yyvsp[(1) - (6)])->appendChild(NEXPAND((yyvsp[(2) - (6)]), (yyvsp[(3) - (6)]), (yyvsp[(4) - (6)])));

    (yyvsp[(1) - (6)])->appendChild((yyvsp[(5) - (6)]));
    (yyvsp[(1) - (6)])->appendChild((yyvsp[(6) - (6)]));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (6)]));
  ;}
    break;

  case 61:
#line 588 "parser.y"
    {
    NTYPE((yyvsp[(1) - (5)]), n_TRY);
    (yyvsp[(1) - (5)])->appendChild(NEXPAND((yyvsp[(2) - (5)]), (yyvsp[(3) - (5)]), (yyvsp[(4) - (5)])));

    (yyvsp[(1) - (5)])->appendChild(NNEW(n_CATCH_LIST));
    (yyvsp[(1) - (5)])->appendChild((yyvsp[(5) - (5)]));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (5)]));
  ;}
    break;

  case 62:
#line 597 "parser.y"
    {
  NTYPE((yyvsp[(1) - (3)]), n_THROW);
  (yyvsp[(1) - (3)])->appendChild((yyvsp[(2) - (3)]));

  (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (3)]));
  NMORE((yyval), (yyvsp[(3) - (3)]));

  ;}
    break;

  case 63:
#line 605 "parser.y"
    {
  NTYPE((yyvsp[(1) - (3)]), n_GOTO);
  NTYPE((yyvsp[(2) - (3)]), n_STRING);
  (yyvsp[(1) - (3)])->appendChild((yyvsp[(2) - (3)]));

  (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (3)]));
  NMORE((yyval), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 64:
#line 616 "parser.y"
    {
    (yyvsp[(1) - (2)])->appendChild((yyvsp[(2) - (2)]));
    (yyval) = (yyvsp[(1) - (2)]);
  ;}
    break;

  case 65:
#line 620 "parser.y"
    {
  (yyval) = NNEW(n_CATCH_LIST);
  (yyval)->appendChild((yyvsp[(1) - (1)]));
;}
    break;

  case 66:
#line 627 "parser.y"
    {
    NTYPE((yyvsp[(1) - (8)]), n_CATCH);
    (yyvsp[(1) - (8)])->appendChild((yyvsp[(3) - (8)]));
    (yyvsp[(1) - (8)])->appendChild(NTYPE((yyvsp[(4) - (8)]), n_VARIABLE));
    (yyvsp[(1) - (8)])->appendChild(NEXPAND((yyvsp[(6) - (8)]), (yyvsp[(7) - (8)]), (yyvsp[(8) - (8)])));
    NMORE((yyvsp[(1) - (8)]), (yyvsp[(8) - (8)]));
    (yyval) = (yyvsp[(1) - (8)]);
  ;}
    break;

  case 67:
#line 638 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 69:
#line 645 "parser.y"
    {
    NTYPE((yyvsp[(1) - (4)]), n_FINALLY);
    (yyvsp[(1) - (4)])->appendChild((yyvsp[(3) - (4)]));
    NMORE((yyvsp[(1) - (4)]), (yyvsp[(4) - (4)]));
    (yyval) = (yyvsp[(1) - (4)]);
  ;}
    break;

  case 70:
#line 654 "parser.y"
    {
    (yyval) = NNEW(n_UNSET_LIST);
    (yyval)->appendChild((yyvsp[(1) - (1)]));
  ;}
    break;

  case 71:
#line 658 "parser.y"
    {
    (yyvsp[(1) - (3)])->appendChild((yyvsp[(3) - (3)]));
    (yyval) = (yyvsp[(1) - (3)]);
  ;}
    break;

  case 75:
#line 677 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 76:
#line 680 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_REFERENCE);
  ;}
    break;

  case 77:
#line 687 "parser.y"
    {
    NSPAN((yyvsp[(1) - (10)]), n_FUNCTION_DECLARATION, (yyvsp[(9) - (10)]));
    (yyvsp[(1) - (10)])->appendChild(NNEW(n_EMPTY));
    (yyvsp[(1) - (10)])->appendChild((yyvsp[(2) - (10)]));
    (yyvsp[(1) - (10)])->appendChild(NTYPE((yyvsp[(3) - (10)]), n_STRING));
    (yyvsp[(1) - (10)])->appendChild(NEXPAND((yyvsp[(4) - (10)]), (yyvsp[(5) - (10)]), (yyvsp[(6) - (10)])));
    (yyvsp[(1) - (10)])->appendChild(NNEW(n_EMPTY));
    (yyvsp[(1) - (10)])->appendChild((yyvsp[(7) - (10)]));
    (yyvsp[(1) - (10)])->appendChild(NEXPAND((yyvsp[(8) - (10)]), (yyvsp[(9) - (10)]), (yyvsp[(10) - (10)])));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (10)]));
  ;}
    break;

  case 78:
#line 703 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_DECLARATION);
    (yyval)->appendChild((yyvsp[(1) - (7)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (7)]), n_CLASS_NAME));
    (yyval)->appendChild((yyvsp[(3) - (7)]));
    (yyval)->appendChild((yyvsp[(4) - (7)]));
    (yyval)->appendChild(NEXPAND((yyvsp[(5) - (7)]), (yyvsp[(6) - (7)]), (yyvsp[(7) - (7)])));
    NMORE((yyval), (yyvsp[(7) - (7)]));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyval));
  ;}
    break;

  case 79:
#line 714 "parser.y"
    {
    (yyval) = NNEW(n_INTERFACE_DECLARATION);
    (yyval)->appendChild(NNEW(n_CLASS_ATTRIBUTES));
    NMORE((yyval), (yyvsp[(1) - (6)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (6)]), n_CLASS_NAME));
    (yyval)->appendChild((yyvsp[(3) - (6)]));
    (yyval)->appendChild(NNEW(n_EMPTY));
    (yyval)->appendChild(NEXPAND((yyvsp[(4) - (6)]), (yyvsp[(5) - (6)]), (yyvsp[(6) - (6)])));
    NMORE((yyval), (yyvsp[(6) - (6)]));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyval));
  ;}
    break;

  case 80:
#line 729 "parser.y"
    {
    NTYPE((yyvsp[(1) - (1)]), n_CLASS_ATTRIBUTES);
    (yyval) = (yyvsp[(1) - (1)]);
  ;}
    break;

  case 81:
#line 733 "parser.y"
    {
    NTYPE((yyvsp[(2) - (2)]), n_CLASS_ATTRIBUTES);
    NMORE((yyvsp[(2) - (2)]), (yyvsp[(1) - (2)]));
    (yyvsp[(2) - (2)])->appendChild(NTYPE((yyvsp[(1) - (2)]), n_STRING));

    (yyval) = (yyvsp[(2) - (2)]);
  ;}
    break;

  case 82:
#line 740 "parser.y"
    {
    NTYPE((yyvsp[(2) - (2)]), n_CLASS_ATTRIBUTES);
    NMORE((yyvsp[(2) - (2)]), (yyvsp[(1) - (2)]));
    (yyvsp[(2) - (2)])->appendChild(NTYPE((yyvsp[(1) - (2)]), n_STRING));

    (yyval) = (yyvsp[(2) - (2)]);
  ;}
    break;

  case 83:
#line 747 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_ATTRIBUTES);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (1)]), n_STRING));
  ;}
    break;

  case 84:
#line 754 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 85:
#line 757 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (2)]), n_EXTENDS_LIST)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 87:
#line 767 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 88:
#line 770 "parser.y"
    {
    NTYPE((yyvsp[(1) - (2)]), n_EXTENDS_LIST);
    (yyvsp[(1) - (2)])->appendChildren((yyvsp[(2) - (2)]));
    (yyval) = (yyvsp[(1) - (2)]);
  ;}
    break;

  case 89:
#line 778 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 90:
#line 781 "parser.y"
    {
    NTYPE((yyvsp[(1) - (2)]), n_IMPLEMENTS_LIST);
    (yyvsp[(1) - (2)])->appendChildren((yyvsp[(2) - (2)]));
    (yyval) = (yyvsp[(1) - (2)]);
  ;}
    break;

  case 91:
#line 789 "parser.y"
    {
    (yyval) = NNEW(n_IMPLEMENTS_LIST)->appendChild((yyvsp[(1) - (1)]));
  ;}
    break;

  case 92:
#line 792 "parser.y"
    {
    (yyval) = (yyvsp[(1) - (3)])->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 93:
#line 798 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 94:
#line 801 "parser.y"
    {
    (yyval) = (yyvsp[(2) - (2)]);
  ;}
    break;

  case 96:
#line 808 "parser.y"
    {
    NTYPE((yyvsp[(1) - (2)]), n_VARIABLE_REFERENCE);
    (yyvsp[(1) - (2)])->appendChild((yyvsp[(2) - (2)]));
    (yyval) = (yyvsp[(1) - (2)]);
  ;}
    break;

  case 98:
#line 817 "parser.y"
    {
  NMORE((yyvsp[(2) - (4)]), (yyvsp[(1) - (4)]));
  NMORE((yyvsp[(2) - (4)]), (yyvsp[(4) - (4)]));
  (yyval) = (yyvsp[(2) - (4)]);
  ;}
    break;

  case 100:
#line 826 "parser.y"
    {
  NMORE((yyvsp[(2) - (4)]), (yyvsp[(1) - (4)]));
  NMORE((yyvsp[(2) - (4)]), (yyvsp[(4) - (4)]));
  (yyval) = (yyvsp[(2) - (4)]);
  ;}
    break;

  case 102:
#line 835 "parser.y"
    {
  NMORE((yyvsp[(2) - (4)]), (yyvsp[(1) - (4)]));
  NMORE((yyvsp[(2) - (4)]), (yyvsp[(4) - (4)]));
  (yyval) = (yyvsp[(2) - (4)]);
  ;}
    break;

  case 103:
#line 843 "parser.y"
    {
    (yyval) = NNEW(n_DECLARE_DECLARATION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (3)]), n_STRING));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
    (yyval) = NNEW(n_DECLARE_DECLARATION_LIST)->appendChild((yyval));
  ;}
    break;

  case 104:
#line 849 "parser.y"
    {
    (yyval) = NNEW(n_DECLARE_DECLARATION);
    (yyval)->appendChild(NTYPE((yyvsp[(3) - (5)]), n_STRING));
    (yyval)->appendChild((yyvsp[(5) - (5)]));

    (yyvsp[(1) - (5)])->appendChild((yyval));
    (yyval) = (yyvsp[(1) - (5)]);
  ;}
    break;

  case 105:
#line 860 "parser.y"
    {
    (yyval) = NEXPAND((yyvsp[(1) - (3)]), (yyvsp[(2) - (3)]), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 106:
#line 863 "parser.y"
    {
    // ...why does this rule exist?

    NTYPE((yyvsp[(2) - (4)]), n_STATEMENT);
    (yyvsp[(1) - (4)])->appendChild(NNEW(n_EMPTY));

    (yyval) = NNEW(n_STATEMENT_LIST)->appendChild((yyvsp[(2) - (4)]));
    (yyval)->appendChildren((yyvsp[(3) - (4)]));
    NEXPAND((yyvsp[(1) - (4)]), (yyval), (yyvsp[(4) - (4)]));
  ;}
    break;

  case 107:
#line 873 "parser.y"
    {
    NMORE((yyvsp[(2) - (4)]), (yyvsp[(4) - (4)]));
    NMORE((yyvsp[(2) - (4)]), (yyvsp[(1) - (4)]));
    (yyval) = (yyvsp[(2) - (4)]);
  ;}
    break;

  case 108:
#line 878 "parser.y"
    {
    NTYPE((yyvsp[(2) - (5)]), n_STATEMENT);
    (yyvsp[(1) - (5)])->appendChild(NNEW(n_EMPTY));

    (yyval) = NNEW(n_STATEMENT_LIST)->appendChild((yyvsp[(2) - (5)]));
    (yyval)->appendChildren((yyvsp[(3) - (5)]));
    NMORE((yyval), (yyvsp[(5) - (5)]));
    NMORE((yyval), (yyvsp[(1) - (5)]));
  ;}
    break;

  case 109:
#line 890 "parser.y"
    {
    (yyval) = NNEW(n_STATEMENT_LIST);
  ;}
    break;

  case 110:
#line 893 "parser.y"
    {
    NTYPE((yyvsp[(2) - (5)]), n_CASE);
    (yyvsp[(2) - (5)])->appendChild((yyvsp[(3) - (5)]));
    (yyvsp[(2) - (5)])->appendChild((yyvsp[(5) - (5)]));

    (yyvsp[(1) - (5)])->appendChild((yyvsp[(2) - (5)]));
    (yyval) = (yyvsp[(1) - (5)]);
  ;}
    break;

  case 111:
#line 901 "parser.y"
    {
    NTYPE((yyvsp[(2) - (4)]), n_DEFAULT);
    (yyvsp[(2) - (4)])->appendChild((yyvsp[(4) - (4)]));

    (yyvsp[(1) - (4)])->appendChild((yyvsp[(2) - (4)]));
    (yyval) = (yyvsp[(1) - (4)]);
  ;}
    break;

  case 115:
#line 917 "parser.y"
    {
  NMORE((yyvsp[(2) - (4)]), (yyvsp[(4) - (4)]));
  NMORE((yyvsp[(2) - (4)]), (yyvsp[(1) - (4)]));
  (yyval) = (yyvsp[(2) - (4)]);
  ;}
    break;

  case 116:
#line 925 "parser.y"
    {
    (yyval) = NNEW(n_CONDITION_LIST);
  ;}
    break;

  case 117:
#line 928 "parser.y"
    {
    NTYPE((yyvsp[(2) - (6)]), n_ELSEIF);
    (yyvsp[(2) - (6)])->appendChild(NSPAN((yyvsp[(3) - (6)]), n_CONTROL_CONDITION, (yyvsp[(5) - (6)]))->appendChild((yyvsp[(4) - (6)])));
    (yyvsp[(2) - (6)])->appendChild((yyvsp[(6) - (6)]));

    (yyval) = (yyvsp[(1) - (6)])->appendChild((yyvsp[(2) - (6)]));
  ;}
    break;

  case 118:
#line 938 "parser.y"
    {
    (yyval) = NNEW(n_CONDITION_LIST);
  ;}
    break;

  case 119:
#line 941 "parser.y"
    {
    NTYPE((yyvsp[(2) - (7)]), n_ELSEIF);
    (yyvsp[(2) - (7)])->appendChild((yyvsp[(4) - (7)]));
    (yyvsp[(2) - (7)])->appendChild((yyvsp[(7) - (7)]));

    (yyval) = (yyvsp[(1) - (7)])->appendChild((yyvsp[(2) - (7)]));
  ;}
    break;

  case 120:
#line 951 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 121:
#line 954 "parser.y"
    {
    NTYPE((yyvsp[(1) - (2)]), n_ELSE);
    (yyvsp[(1) - (2)])->appendChild((yyvsp[(2) - (2)]));
    (yyval) = (yyvsp[(1) - (2)]);
  ;}
    break;

  case 122:
#line 962 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 123:
#line 965 "parser.y"
    {
    NTYPE((yyvsp[(1) - (3)]), n_ELSE);
    (yyvsp[(1) - (3)])->appendChild((yyvsp[(3) - (3)]));
    (yyval) = (yyvsp[(1) - (3)]);
  ;}
    break;

  case 125:
#line 974 "parser.y"
    {
    (yyval) = NNEW(n_DECLARATION_PARAMETER_LIST);
  ;}
    break;

  case 126:
#line 980 "parser.y"
    {
    (yyval) = NNEW(n_DECLARATION_PARAMETER);
    (yyval)->appendChild((yyvsp[(1) - (2)]));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
    (yyval)->appendChild(NNEW(n_EMPTY));

    (yyval) = NNEW(n_DECLARATION_PARAMETER_LIST)->appendChild((yyval));
  ;}
    break;

  case 127:
#line 988 "parser.y"
    {
    (yyval) = NNEW(n_DECLARATION_PARAMETER);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_VARIABLE_REFERENCE));
      (yyvsp[(2) - (3)])->appendChild((yyvsp[(3) - (3)]));
    (yyval)->appendChild(NNEW(n_EMPTY));

    (yyval) = NNEW(n_DECLARATION_PARAMETER_LIST)->appendChild((yyval));
  ;}
    break;

  case 128:
#line 997 "parser.y"
    {
    (yyval) = NNEW(n_DECLARATION_PARAMETER);
    (yyval)->appendChild((yyvsp[(1) - (5)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (5)]), n_VARIABLE_REFERENCE));
      (yyvsp[(2) - (5)])->appendChild((yyvsp[(3) - (5)]));
    (yyval)->appendChild((yyvsp[(5) - (5)]));

    (yyval) = NNEW(n_DECLARATION_PARAMETER_LIST)->appendChild((yyval));
  ;}
    break;

  case 129:
#line 1006 "parser.y"
    {
    (yyval) = NNEW(n_DECLARATION_PARAMETER);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild((yyvsp[(2) - (4)]));
    (yyval)->appendChild((yyvsp[(4) - (4)]));

    (yyval) = NNEW(n_DECLARATION_PARAMETER_LIST)->appendChild((yyval));
  ;}
    break;

  case 130:
#line 1014 "parser.y"
    {
    (yyval) = NNEW(n_DECLARATION_PARAMETER);
    (yyval)->appendChild((yyvsp[(3) - (4)]));
    (yyval)->appendChild((yyvsp[(4) - (4)]));
    (yyval)->appendChild(NNEW(n_EMPTY));

    (yyval) = (yyvsp[(1) - (4)])->appendChild((yyval));
  ;}
    break;

  case 131:
#line 1022 "parser.y"
    {
    (yyval) = NNEW(n_DECLARATION_PARAMETER);
    (yyval)->appendChild((yyvsp[(3) - (5)]));
    (yyval)->appendChild(NTYPE((yyvsp[(4) - (5)]), n_VARIABLE_REFERENCE));
      (yyvsp[(4) - (5)])->appendChild((yyvsp[(5) - (5)]));
    (yyval)->appendChild(NNEW(n_EMPTY));

    (yyval) = (yyvsp[(1) - (5)])->appendChild((yyval));
  ;}
    break;

  case 132:
#line 1032 "parser.y"
    {
    (yyval) = NNEW(n_DECLARATION_PARAMETER);
    (yyval)->appendChild((yyvsp[(3) - (7)]));
    (yyval)->appendChild(NTYPE((yyvsp[(4) - (7)]), n_VARIABLE_REFERENCE));
      (yyvsp[(4) - (7)])->appendChild((yyvsp[(5) - (7)]));
    (yyval)->appendChild((yyvsp[(7) - (7)]));

    (yyval) = (yyvsp[(1) - (7)])->appendChild((yyval));
  ;}
    break;

  case 133:
#line 1042 "parser.y"
    {
    (yyval) = NNEW(n_DECLARATION_PARAMETER);
    (yyval)->appendChild((yyvsp[(3) - (6)]));
    (yyval)->appendChild((yyvsp[(4) - (6)]));
    (yyval)->appendChild((yyvsp[(6) - (6)]));

    (yyval) = (yyvsp[(1) - (6)])->appendChild((yyval));
  ;}
    break;

  case 134:
#line 1053 "parser.y"
    {
    NTYPE((yyvsp[(1) - (2)]), n_UNPACK);
    (yyval) = (yyvsp[(1) - (2)])->appendChild(NTYPE((yyvsp[(2) - (2)]), n_VARIABLE));
  ;}
    break;

  case 135:
#line 1057 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_VARIABLE);
  ;}
    break;

  case 136:
#line 1063 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 138:
#line 1067 "parser.y"
    {
    (yyval) = NNEW(n_NULLABLE_TYPE);
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 139:
#line 1074 "parser.y"
    {
    (yyval) = (yyvsp[(1) - (1)]);
  ;}
    break;

  case 140:
#line 1077 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_TYPE_NAME);
  ;}
    break;

  case 141:
#line 1080 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_TYPE_NAME);
  ;}
    break;

  case 142:
#line 1086 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 143:
#line 1089 "parser.y"
    {
    (yyval) = NNEW(n_DECLARATION_RETURN);
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 145:
#line 1097 "parser.y"
    {
    (yyval) = NNEW(n_CALL_PARAMETER_LIST);
  ;}
    break;

  case 146:
#line 1103 "parser.y"
    {
    (yyval) = NNEW(n_CALL_PARAMETER_LIST)->appendChild((yyvsp[(1) - (1)]));
  ;}
    break;

  case 147:
#line 1106 "parser.y"
    {
    (yyval) = (yyvsp[(1) - (3)])->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 149:
#line 1113 "parser.y"
    {
    NTYPE((yyvsp[(1) - (2)]), n_UNPACK);
    (yyval) = (yyvsp[(1) - (2)])->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 150:
#line 1117 "parser.y"
    {
    NTYPE((yyvsp[(1) - (2)]), n_VARIABLE_REFERENCE);
    (yyval) = (yyvsp[(1) - (2)])->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 151:
#line 1124 "parser.y"
    {
    (yyvsp[(1) - (3)])->appendChild((yyvsp[(3) - (3)]));
    (yyval) = (yyvsp[(1) - (3)]);
  ;}
    break;

  case 152:
#line 1128 "parser.y"
    {
    (yyval) = NNEW(n_GLOBAL_DECLARATION_LIST);
    (yyval)->appendChild((yyvsp[(1) - (1)]));
  ;}
    break;

  case 153:
#line 1135 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_VARIABLE);
  ;}
    break;

  case 154:
#line 1138 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (2)]), n_VARIABLE_VARIABLE);
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 155:
#line 1142 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (4)]), n_VARIABLE_VARIABLE);
    (yyval)->appendChild((yyvsp[(3) - (4)]));
  ;}
    break;

  case 156:
#line 1149 "parser.y"
    {
    NTYPE((yyvsp[(3) - (3)]), n_VARIABLE);
    (yyval) = NNEW(n_STATIC_DECLARATION);
    (yyval)->appendChild((yyvsp[(3) - (3)]));
    (yyval)->appendChild(NNEW(n_EMPTY));

    (yyval) = (yyvsp[(1) - (3)])->appendChild((yyval));
  ;}
    break;

  case 157:
#line 1157 "parser.y"
    {
    NTYPE((yyvsp[(3) - (5)]), n_VARIABLE);
    (yyval) = NNEW(n_STATIC_DECLARATION);
    (yyval)->appendChild((yyvsp[(3) - (5)]));
    (yyval)->appendChild((yyvsp[(5) - (5)]));

    (yyval) = (yyvsp[(1) - (5)])->appendChild((yyval));
  ;}
    break;

  case 158:
#line 1165 "parser.y"
    {
    NTYPE((yyvsp[(1) - (1)]), n_VARIABLE);
    (yyval) = NNEW(n_STATIC_DECLARATION);
    (yyval)->appendChild((yyvsp[(1) - (1)]));
    (yyval)->appendChild(NNEW(n_EMPTY));

    (yyval) = NNEW(n_STATIC_DECLARATION_LIST)->appendChild((yyval));
  ;}
    break;

  case 159:
#line 1173 "parser.y"
    {
    NTYPE((yyvsp[(1) - (3)]), n_VARIABLE);
    (yyval) = NNEW(n_STATIC_DECLARATION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild((yyvsp[(3) - (3)]));

    (yyval) = NNEW(n_STATIC_DECLARATION_LIST)->appendChild((yyval));
  ;}
    break;

  case 160:
#line 1184 "parser.y"
    {
    (yyval) = (yyvsp[(1) - (2)])->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 161:
#line 1187 "parser.y"
    {
    (yyval) = NNEW(n_STATEMENT_LIST);
  ;}
    break;

  case 162:
#line 1193 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_MEMBER_DECLARATION_LIST);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChildren((yyvsp[(2) - (3)]));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyval));
    NMORE((yyval), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 163:
#line 1201 "parser.y"
    {
    (yyval) = NNEW(n_STATEMENT)->appendChild((yyvsp[(1) - (2)]));
    NMORE((yyval), (yyvsp[(2) - (2)]));
  ;}
    break;

  case 164:
#line 1205 "parser.y"
    {
    (yyval) = (yyvsp[(1) - (1)]);
  ;}
    break;

  case 165:
#line 1208 "parser.y"
    {
    /* empty */
  ;}
    break;

  case 166:
#line 1210 "parser.y"
    {
    (yyval) = NNEW(n_METHOD_DECLARATION);
    NMORE((yyval), (yyvsp[(2) - (10)]));
    (yyval)->appendChild((yyvsp[(1) - (10)]));
    (yyval)->appendChild((yyvsp[(4) - (10)]));
    (yyval)->appendChild(NTYPE((yyvsp[(5) - (10)]), n_STRING));
    (yyval)->appendChild(NEXPAND((yyvsp[(6) - (10)]), (yyvsp[(7) - (10)]), (yyvsp[(8) - (10)])));
    (yyval)->appendChild(NNEW(n_EMPTY));
    (yyval)->appendChild((yyvsp[(9) - (10)]));
    (yyval)->appendChild((yyvsp[(10) - (10)]));

    (yyval) = NNEW(n_STATEMENT)->appendChild((yyval));
  ;}
    break;

  case 167:
#line 1226 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (3)]), n_TRAIT_USE);
    (yyval)->appendChildren((yyvsp[(2) - (3)]));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 168:
#line 1234 "parser.y"
    {
    (yyval) = NNEW(n_TRAIT_USE_LIST)->appendChild((yyvsp[(1) - (1)]));
  ;}
    break;

  case 169:
#line 1237 "parser.y"
    {
    (yyval) = (yyvsp[(1) - (3)])->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 170:
#line 1243 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 171:
#line 1246 "parser.y"
    {
    (yyval) = NEXPAND((yyvsp[(1) - (3)]), (yyvsp[(2) - (3)]), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 172:
#line 1252 "parser.y"
    {
    (yyval) = NNEW(n_TRAIT_ADAPTATION_LIST);
  ;}
    break;

  case 173:
#line 1255 "parser.y"
    {
    (yyval) = (yyvsp[(1) - (1)]);
  ;}
    break;

  case 174:
#line 1261 "parser.y"
    {
    (yyval) = NNEW(n_TRAIT_ADAPTATION_LIST);
    (yyval)->appendChild((yyvsp[(1) - (1)]));
  ;}
    break;

  case 175:
#line 1265 "parser.y"
    {
    (yyvsp[(1) - (2)])->appendChild((yyvsp[(2) - (2)]));
    (yyval) = (yyvsp[(1) - (2)]);
  ;}
    break;

  case 176:
#line 1272 "parser.y"
    {
    (yyval) = NMORE((yyvsp[(1) - (2)]), (yyvsp[(2) - (2)]));
  ;}
    break;

  case 177:
#line 1275 "parser.y"
    {
    (yyval) = NMORE((yyvsp[(1) - (2)]), (yyvsp[(2) - (2)]));
  ;}
    break;

  case 178:
#line 1281 "parser.y"
    {
    (yyval) = NNEW(n_TRAIT_INSTEADOF);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 179:
#line 1289 "parser.y"
    {
    (yyval) = NNEW(n_TRAIT_REFERENCE_LIST);
    (yyval)->appendChild((yyvsp[(1) - (1)]));
  ;}
    break;

  case 180:
#line 1293 "parser.y"
    {
    (yyvsp[(1) - (3)])->appendChild((yyvsp[(3) - (3)]));
    (yyval) = (yyvsp[(1) - (3)]);
  ;}
    break;

  case 181:
#line 1300 "parser.y"
    {
    (yyval) = NNEW(n_TRAIT_METHOD_REFERENCE);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (1)]), n_STRING));
  ;}
    break;

  case 182:
#line 1304 "parser.y"
    {
    (yyval) = (yyvsp[(1) - (1)]);
  ;}
    break;

  case 183:
#line 1310 "parser.y"
    {
    NTYPE((yyvsp[(2) - (3)]), n_TRAIT_METHOD_REFERENCE);
    NEXPAND((yyvsp[(1) - (3)]), (yyvsp[(2) - (3)]), NTYPE((yyvsp[(3) - (3)]), n_STRING));
    (yyval) = (yyvsp[(2) - (3)]);
  ;}
    break;

  case 184:
#line 1318 "parser.y"
    {
    (yyval) = NNEW(n_TRAIT_AS);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild((yyvsp[(3) - (4)]));
    (yyval)->appendChild(NTYPE((yyvsp[(4) - (4)]), n_STRING));
  ;}
    break;

  case 185:
#line 1324 "parser.y"
    {
    (yyval) = NNEW(n_TRAIT_AS);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
    (yyval)->appendChild(NNEW(n_EMPTY));
  ;}
    break;

  case 186:
#line 1333 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 187:
#line 1336 "parser.y"
    {
    (yyval) = NNEW(n_METHOD_MODIFIER_LIST);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (1)]), n_STRING));
  ;}
    break;

  case 188:
#line 1344 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 189:
#line 1347 "parser.y"
    {
    (yyval) = NEXPAND((yyvsp[(1) - (3)]), (yyvsp[(2) - (3)]), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 191:
#line 1354 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_MEMBER_MODIFIER_LIST);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (1)]), n_STRING));
  ;}
    break;

  case 192:
#line 1361 "parser.y"
    {
    (yyval) = NNEW(n_METHOD_MODIFIER_LIST);
  ;}
    break;

  case 193:
#line 1364 "parser.y"
    {
    NTYPE((yyvsp[(1) - (1)]), n_METHOD_MODIFIER_LIST);
    (yyval) = (yyvsp[(1) - (1)]);
  ;}
    break;

  case 194:
#line 1371 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_MEMBER_MODIFIER_LIST);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (1)]), n_STRING));
  ;}
    break;

  case 195:
#line 1375 "parser.y"
    {
    (yyval) = (yyvsp[(1) - (2)])->appendChild(NTYPE((yyvsp[(2) - (2)]), n_STRING));
  ;}
    break;

  case 202:
#line 1390 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_MEMBER_DECLARATION);
    (yyval)->appendChild(NTYPE((yyvsp[(3) - (3)]), n_VARIABLE));
    (yyval)->appendChild(NNEW(n_EMPTY));

    (yyval) = (yyvsp[(1) - (3)])->appendChild((yyval));
  ;}
    break;

  case 203:
#line 1397 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_MEMBER_DECLARATION);
    (yyval)->appendChild(NTYPE((yyvsp[(3) - (5)]), n_VARIABLE));
    (yyval)->appendChild((yyvsp[(5) - (5)]));

    (yyval) = (yyvsp[(1) - (5)])->appendChild((yyval));
  ;}
    break;

  case 204:
#line 1404 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_MEMBER_DECLARATION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (1)]), n_VARIABLE));
    (yyval)->appendChild(NNEW(n_EMPTY));

    (yyval) = NNEW(n_CLASS_MEMBER_DECLARATION_LIST)->appendChild((yyval));
  ;}
    break;

  case 205:
#line 1411 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_MEMBER_DECLARATION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (3)]), n_VARIABLE));
    (yyval)->appendChild((yyvsp[(3) - (3)]));

    (yyval) = NNEW(n_CLASS_MEMBER_DECLARATION_LIST)->appendChild((yyval));
  ;}
    break;

  case 206:
#line 1421 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_CONSTANT_DECLARATION);
    (yyval)->appendChild(NTYPE((yyvsp[(3) - (5)]), n_STRING));
    (yyval)->appendChild((yyvsp[(5) - (5)]));

    (yyvsp[(1) - (5)])->appendChild((yyval));

    (yyval) = (yyvsp[(1) - (5)]);
  ;}
    break;

  case 207:
#line 1430 "parser.y"
    {
    NTYPE((yyvsp[(1) - (4)]), n_CLASS_CONSTANT_DECLARATION_LIST);
    (yyval) = NNEW(n_CLASS_CONSTANT_DECLARATION);
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (4)]), n_STRING));
    (yyval)->appendChild((yyvsp[(4) - (4)]));
    (yyvsp[(1) - (4)])->appendChild((yyval));

    (yyval) = (yyvsp[(1) - (4)]);
  ;}
    break;

  case 208:
#line 1442 "parser.y"
    {
    (yyvsp[(1) - (3)])->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 209:
#line 1445 "parser.y"
    {
    (yyval) = NNEW(n_ECHO_LIST);
    (yyval)->appendChild((yyvsp[(1) - (1)]));
  ;}
    break;

  case 210:
#line 1452 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 212:
#line 1460 "parser.y"
    {
    (yyvsp[(1) - (3)])->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 213:
#line 1463 "parser.y"
    {
    (yyval) = NNEW(n_EXPRESSION_LIST);
    (yyval)->appendChild((yyvsp[(1) - (1)]));
  ;}
    break;

  case 214:
#line 1470 "parser.y"
    {
    NTYPE((yyvsp[(1) - (6)]), n_LIST);
    (yyvsp[(1) - (6)])->appendChild(NEXPAND((yyvsp[(2) - (6)]), (yyvsp[(3) - (6)]), (yyvsp[(4) - (6)])));
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (6)]));
    (yyval)->appendChild(NTYPE((yyvsp[(5) - (6)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(6) - (6)]));
  ;}
    break;

  case 215:
#line 1478 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 216:
#line 1484 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (4)]), n_OPERATOR));

    NTYPE((yyvsp[(3) - (4)]), n_VARIABLE_REFERENCE);
    (yyvsp[(3) - (4)])->appendChild((yyvsp[(4) - (4)]));

    (yyval)->appendChild((yyvsp[(3) - (4)]));
  ;}
    break;

  case 217:
#line 1494 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (6)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (6)]), n_OPERATOR));

    NTYPE((yyvsp[(4) - (6)]), n_NEW);
    (yyvsp[(4) - (6)])->appendChild((yyvsp[(5) - (6)]));
    (yyvsp[(4) - (6)])->appendChild((yyvsp[(6) - (6)]));

    NTYPE((yyvsp[(3) - (6)]), n_VARIABLE_REFERENCE);
    (yyvsp[(3) - (6)])->appendChild((yyvsp[(4) - (6)]));

    (yyval)->appendChild((yyvsp[(3) - (6)]));
  ;}
    break;

  case 218:
#line 1508 "parser.y"
    {
    (yyval) = NNEW(n_UNARY_PREFIX_EXPRESSION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 219:
#line 1513 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 220:
#line 1519 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 221:
#line 1525 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 222:
#line 1531 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 223:
#line 1537 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 224:
#line 1543 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 225:
#line 1549 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 226:
#line 1555 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 227:
#line 1561 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 228:
#line 1567 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 229:
#line 1573 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 230:
#line 1579 "parser.y"
    {
    (yyval) = NNEW(n_UNARY_POSTFIX_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (2)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (2)]), n_OPERATOR));
  ;}
    break;

  case 231:
#line 1584 "parser.y"
    {
    (yyval) = NNEW(n_UNARY_PREFIX_EXPRESSION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 232:
#line 1589 "parser.y"
    {
    (yyval) = NNEW(n_UNARY_POSTFIX_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (2)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (2)]), n_OPERATOR));
  ;}
    break;

  case 233:
#line 1594 "parser.y"
    {
    (yyval) = NNEW(n_UNARY_PREFIX_EXPRESSION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 234:
#line 1599 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 235:
#line 1605 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 236:
#line 1611 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 237:
#line 1617 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 238:
#line 1623 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 239:
#line 1629 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 240:
#line 1635 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 241:
#line 1641 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 242:
#line 1647 "parser.y"
    {

    /* The concatenation operator generates n_CONCATENATION_LIST instead of
       n_BINARY_EXPRESSION because we tend to run into stack depth issues in a
       lot of real-world cases otherwise (e.g., in PHP and JSON decoders). */

    if ((yyvsp[(1) - (3)])->type == n_CONCATENATION_LIST && (yyvsp[(3) - (3)])->type == n_CONCATENATION_LIST) {
      (yyvsp[(1) - (3)])->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
      (yyvsp[(1) - (3)])->appendChildren((yyvsp[(3) - (3)]));
      (yyval) = (yyvsp[(1) - (3)]);
    } else if ((yyvsp[(1) - (3)])->type == n_CONCATENATION_LIST) {
      (yyvsp[(1) - (3)])->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
      (yyvsp[(1) - (3)])->appendChild((yyvsp[(3) - (3)]));
      (yyval) = (yyvsp[(1) - (3)]);
    } else if ((yyvsp[(3) - (3)])->type == n_CONCATENATION_LIST) {
      (yyval) = NNEW(n_CONCATENATION_LIST);
      (yyval)->appendChild((yyvsp[(1) - (3)]));
      (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
      (yyval)->appendChildren((yyvsp[(3) - (3)]));
    } else {
      (yyval) = NNEW(n_CONCATENATION_LIST);
      (yyval)->appendChild((yyvsp[(1) - (3)]));
      (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
      (yyval)->appendChild((yyvsp[(3) - (3)]));
    }
  ;}
    break;

  case 243:
#line 1673 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 244:
#line 1679 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 245:
#line 1685 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 246:
#line 1691 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 247:
#line 1697 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 248:
#line 1703 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 249:
#line 1709 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 250:
#line 1715 "parser.y"
    {
    (yyval) = NNEW(n_UNARY_PREFIX_EXPRESSION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 251:
#line 1720 "parser.y"
    {
    (yyval) = NNEW(n_UNARY_PREFIX_EXPRESSION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 252:
#line 1725 "parser.y"
    {
    (yyval) = NNEW(n_UNARY_PREFIX_EXPRESSION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 253:
#line 1730 "parser.y"
    {
    (yyval) = NNEW(n_UNARY_PREFIX_EXPRESSION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 254:
#line 1735 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 255:
#line 1741 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 256:
#line 1747 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 257:
#line 1753 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 258:
#line 1759 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 259:
#line 1765 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 260:
#line 1771 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 261:
#line 1777 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 262:
#line 1783 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 263:
#line 1789 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 266:
#line 1797 "parser.y"
    {
    (yyval) = NNEW(n_TERNARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (5)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (5)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (5)]));
    (yyval)->appendChild(NTYPE((yyvsp[(4) - (5)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(5) - (5)]));
  ;}
    break;

  case 267:
#line 1805 "parser.y"
    {
    (yyval) = NNEW(n_TERNARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (4)]), n_OPERATOR));
    (yyval)->appendChild(NNEW(n_EMPTY));
    (yyval)->appendChild(NTYPE((yyvsp[(3) - (4)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(4) - (4)]));
  ;}
    break;

  case 268:
#line 1813 "parser.y"
    {
    (yyval) = NNEW(n_BINARY_EXPRESSION);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(2) - (3)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 270:
#line 1820 "parser.y"
    {
    (yyval) = NNEW(n_CAST_EXPRESSION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_CAST));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 271:
#line 1825 "parser.y"
    {
    (yyval) = NNEW(n_CAST_EXPRESSION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_CAST));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 272:
#line 1830 "parser.y"
    {
    (yyval) = NNEW(n_CAST_EXPRESSION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_CAST));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 273:
#line 1835 "parser.y"
    {
    (yyval) = NNEW(n_CAST_EXPRESSION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_CAST));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 274:
#line 1840 "parser.y"
    {
    (yyval) = NNEW(n_CAST_EXPRESSION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_CAST));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 275:
#line 1845 "parser.y"
    {
    (yyval) = NNEW(n_CAST_EXPRESSION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_CAST));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 276:
#line 1850 "parser.y"
    {
    (yyval) = NNEW(n_CAST_EXPRESSION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_CAST));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 277:
#line 1855 "parser.y"
    {
    (yyval) = NNEW(n_UNARY_PREFIX_EXPRESSION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 278:
#line 1860 "parser.y"
    {
    (yyval) = NNEW(n_UNARY_PREFIX_EXPRESSION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 279:
#line 1865 "parser.y"
    {
    NTYPE((yyvsp[(1) - (1)]), n_BACKTICKS_EXPRESSION);
    (yyval) = (yyvsp[(1) - (1)]);
  ;}
    break;

  case 283:
#line 1872 "parser.y"
    {
    (yyval) = NNEW(n_UNARY_PREFIX_EXPRESSION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 284:
#line 1877 "parser.y"
    {
    NTYPE((yyvsp[(1) - (1)]), n_YIELD);
    (yyvsp[(1) - (1)])->appendChild(NNEW(n_EMPTY));
    (yyvsp[(1) - (1)])->appendChild(NNEW(n_EMPTY));
    (yyval) = (yyvsp[(1) - (1)]);
  ;}
    break;

  case 285:
#line 1886 "parser.y"
    {
    NSPAN((yyvsp[(1) - (10)]), n_FUNCTION_DECLARATION, (yyvsp[(9) - (10)]));
    (yyvsp[(1) - (10)])->appendChild(NNEW(n_EMPTY));
    (yyvsp[(1) - (10)])->appendChild((yyvsp[(2) - (10)]));
    (yyvsp[(1) - (10)])->appendChild(NNEW(n_EMPTY));
    (yyvsp[(1) - (10)])->appendChild(NEXPAND((yyvsp[(3) - (10)]), (yyvsp[(4) - (10)]), (yyvsp[(5) - (10)])));
    (yyvsp[(1) - (10)])->appendChild((yyvsp[(6) - (10)]));
    (yyvsp[(1) - (10)])->appendChild((yyvsp[(7) - (10)]));
    (yyvsp[(1) - (10)])->appendChild(NEXPAND((yyvsp[(8) - (10)]), (yyvsp[(9) - (10)]), (yyvsp[(10) - (10)])));

    (yyval) = (yyvsp[(1) - (10)]);
  ;}
    break;

  case 286:
#line 1901 "parser.y"
    {
    NSPAN((yyvsp[(2) - (11)]), n_FUNCTION_DECLARATION, (yyvsp[(10) - (11)]));
    NMORE((yyvsp[(2) - (11)]), (yyvsp[(1) - (11)]));

    (yyval) = NNEW(n_FUNCTION_MODIFIER_LIST);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (11)]), n_STRING));
    (yyvsp[(2) - (11)])->appendChild((yyvsp[(1) - (11)]));

    (yyvsp[(2) - (11)])->appendChild(NNEW(n_EMPTY));
    (yyvsp[(2) - (11)])->appendChild((yyvsp[(3) - (11)]));
    (yyvsp[(2) - (11)])->appendChild(NNEW(n_EMPTY));
    (yyvsp[(2) - (11)])->appendChild(NEXPAND((yyvsp[(4) - (11)]), (yyvsp[(5) - (11)]), (yyvsp[(6) - (11)])));
    (yyvsp[(2) - (11)])->appendChild((yyvsp[(7) - (11)]));
    (yyvsp[(2) - (11)])->appendChild((yyvsp[(8) - (11)]));
    (yyvsp[(2) - (11)])->appendChild(NEXPAND((yyvsp[(9) - (11)]), (yyvsp[(10) - (11)]), (yyvsp[(11) - (11)])));

    (yyval) = (yyvsp[(2) - (11)]);
  ;}
    break;

  case 287:
#line 1922 "parser.y"
    {
    NTYPE((yyvsp[(1) - (2)]), n_YIELD);
    (yyvsp[(2) - (2)])->appendChild(NNEW(n_EMPTY));
    (yyvsp[(1) - (2)])->appendChild((yyvsp[(2) - (2)]));
    (yyval) = (yyvsp[(1) - (2)]);
  ;}
    break;

  case 288:
#line 1928 "parser.y"
    {
    NTYPE((yyvsp[(1) - (2)]), n_YIELD);
    (yyvsp[(2) - (2)])->appendChild(NNEW(n_EMPTY));
    (yyvsp[(1) - (2)])->appendChild((yyvsp[(2) - (2)]));
    (yyval) = (yyvsp[(1) - (2)]);
  ;}
    break;

  case 289:
#line 1934 "parser.y"
    {
    NTYPE((yyvsp[(1) - (4)]), n_YIELD);
    (yyvsp[(1) - (4)])->appendChild((yyvsp[(2) - (4)]));
    (yyvsp[(1) - (4)])->appendChild((yyvsp[(4) - (4)]));
    (yyval) = (yyvsp[(1) - (4)]);
  ;}
    break;

  case 290:
#line 1940 "parser.y"
    {
    NTYPE((yyvsp[(1) - (4)]), n_YIELD);
    (yyvsp[(1) - (4)])->appendChild((yyvsp[(2) - (4)]));
    (yyvsp[(1) - (4)])->appendChild((yyvsp[(4) - (4)]));
    (yyval) = (yyvsp[(1) - (4)]);
  ;}
    break;

  case 292:
#line 1953 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 293:
#line 1956 "parser.y"
    {
    NTYPE((yyvsp[(1) - (4)]), n_LEXICAL_VARIABLE_LIST);
    (yyvsp[(1) - (4)])->appendChildren((yyvsp[(3) - (4)]));
    (yyval) = (yyvsp[(1) - (4)]);
  ;}
    break;

  case 294:
#line 1964 "parser.y"
    {
    (yyval) = (yyvsp[(1) - (3)])->appendChild(NTYPE((yyvsp[(3) - (3)]), n_VARIABLE));
  ;}
    break;

  case 295:
#line 1967 "parser.y"
    {
    NTYPE((yyvsp[(3) - (4)]), n_VARIABLE_REFERENCE);
    (yyvsp[(3) - (4)])->appendChild(NTYPE((yyvsp[(4) - (4)]), n_VARIABLE));
    (yyval) = (yyvsp[(1) - (4)])->appendChild((yyvsp[(3) - (4)]));
  ;}
    break;

  case 296:
#line 1972 "parser.y"
    {
    (yyval) = NNEW(n_LEXICAL_VARIABLE_LIST);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (1)]), n_VARIABLE));
  ;}
    break;

  case 297:
#line 1976 "parser.y"
    {
    NTYPE((yyvsp[(1) - (2)]), n_VARIABLE_REFERENCE);
    (yyvsp[(1) - (2)])->appendChild(NTYPE((yyvsp[(2) - (2)]), n_VARIABLE));
    (yyval) = NNEW(n_LEXICAL_VARIABLE_LIST);
    (yyval)->appendChild((yyvsp[(1) - (2)]));
  ;}
    break;

  case 298:
#line 1985 "parser.y"
    {
    (yyval) = NNEW(n_FUNCTION_CALL);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild(NEXPAND((yyvsp[(2) - (4)]), (yyvsp[(3) - (4)]), (yyvsp[(4) - (4)])));
  ;}
    break;

  case 299:
#line 1991 "parser.y"
    {
    NMORE((yyvsp[(3) - (6)]), (yyvsp[(1) - (6)]));
    (yyval) = NNEW(n_FUNCTION_CALL);
    (yyval)->appendChild((yyvsp[(3) - (6)]));
    (yyval)->appendChild(NEXPAND((yyvsp[(4) - (6)]), (yyvsp[(5) - (6)]), (yyvsp[(6) - (6)])));
  ;}
    break;

  case 300:
#line 1997 "parser.y"
    {
    NMORE((yyvsp[(2) - (5)]), (yyvsp[(1) - (5)]));
    (yyval) = NNEW(n_FUNCTION_CALL);
    (yyval)->appendChild((yyvsp[(2) - (5)]));
    (yyval)->appendChild(NEXPAND((yyvsp[(3) - (5)]), (yyvsp[(4) - (5)]), (yyvsp[(5) - (5)])));
  ;}
    break;

  case 301:
#line 2004 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_STATIC_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (6)]));
    (yyval)->appendChild(NTYPE((yyvsp[(3) - (6)]), n_STRING));

    (yyval) = NNEW(n_FUNCTION_CALL)->appendChild((yyval));
    (yyval)->appendChild(NEXPAND((yyvsp[(4) - (6)]), (yyvsp[(5) - (6)]), (yyvsp[(6) - (6)])));
  ;}
    break;

  case 302:
#line 2013 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_STATIC_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (6)]));
    (yyval)->appendChild(NTYPE((yyvsp[(3) - (6)]), n_STRING));

    (yyval) = NNEW(n_FUNCTION_CALL)->appendChild((yyval));
    (yyval)->appendChild(NEXPAND((yyvsp[(4) - (6)]), (yyvsp[(5) - (6)]), (yyvsp[(6) - (6)])));
  ;}
    break;

  case 303:
#line 2022 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_STATIC_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (6)]));
    (yyval)->appendChild(NTYPE((yyvsp[(3) - (6)]), n_STRING));

    (yyval) = NNEW(n_FUNCTION_CALL)->appendChild((yyval));
    (yyval)->appendChild(NEXPAND((yyvsp[(4) - (6)]), (yyvsp[(5) - (6)]), (yyvsp[(6) - (6)])));
  ;}
    break;

  case 304:
#line 2031 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_STATIC_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (6)]));
    (yyval)->appendChild(NTYPE((yyvsp[(3) - (6)]), n_STRING));

    (yyval) = NNEW(n_FUNCTION_CALL)->appendChild((yyval));
    (yyval)->appendChild(NEXPAND((yyvsp[(4) - (6)]), (yyvsp[(5) - (6)]), (yyvsp[(6) - (6)])));
  ;}
    break;

  case 305:
#line 2039 "parser.y"
    {
    (yyval) = NNEW(n_FUNCTION_CALL);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild(NEXPAND((yyvsp[(2) - (4)]), (yyvsp[(3) - (4)]), (yyvsp[(4) - (4)])));
  ;}
    break;

  case 306:
#line 2047 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_CLASS_NAME);
  ;}
    break;

  case 307:
#line 2050 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_CLASS_NAME);
  ;}
    break;

  case 308:
#line 2053 "parser.y"
    {
    NMORE((yyvsp[(3) - (3)]), (yyvsp[(1) - (3)]));
    (yyval) = NTYPE((yyvsp[(3) - (3)]), n_CLASS_NAME);
  ;}
    break;

  case 309:
#line 2057 "parser.y"
    {
    NMORE((yyvsp[(2) - (2)]), (yyvsp[(1) - (2)]));
    (yyval) = NTYPE((yyvsp[(2) - (2)]), n_CLASS_NAME);
  ;}
    break;

  case 310:
#line 2064 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_CLASS_NAME);
  ;}
    break;

  case 311:
#line 2067 "parser.y"
    {
    NMORE((yyvsp[(3) - (3)]), (yyvsp[(1) - (3)]));
    (yyval) = NTYPE((yyvsp[(3) - (3)]), n_CLASS_NAME);
  ;}
    break;

  case 312:
#line 2071 "parser.y"
    {
    NMORE((yyvsp[(2) - (2)]), (yyvsp[(1) - (2)]));
    (yyval) = NTYPE((yyvsp[(2) - (2)]), n_CLASS_NAME);
  ;}
    break;

  case 315:
#line 2086 "parser.y"
    {
    (yyval) = NNEW(n_OBJECT_PROPERTY_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild((yyvsp[(3) - (4)]));
    for (xhpast::node_list_t::iterator ii = (yyvsp[(4) - (4)])->children.begin();
      ii != (yyvsp[(4) - (4)])->children.end();
      ++ii) {

      (yyval) = NNEW(n_OBJECT_PROPERTY_ACCESS)->appendChild((yyval));
      (yyval)->appendChild(*ii);
    }
  ;}
    break;

  case 317:
#line 2102 "parser.y"
    {
    (yyval) = (yyvsp[(1) - (2)])->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 318:
#line 2105 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 319:
#line 2111 "parser.y"
    {
    (yyval) = (yyvsp[(2) - (2)]);
  ;}
    break;

  case 320:
#line 2117 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 321:
#line 2120 "parser.y"
    {
    NSPAN((yyvsp[(1) - (2)]), n_EMPTY, (yyvsp[(2) - (2)]));
    (yyval) = (yyvsp[(1) - (2)]);
  ;}
    break;

  case 322:
#line 2124 "parser.y"
    {
    NSPAN((yyvsp[(1) - (3)]), n_PARENTHETICAL_EXPRESSION, (yyvsp[(3) - (3)]));
    (yyvsp[(1) - (3)])->appendChild((yyvsp[(2) - (3)]));
    (yyval) = (yyvsp[(1) - (3)]);
  ;}
    break;

  case 323:
#line 2132 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 324:
#line 2135 "parser.y"
    {
    (yyval) = NEXPAND((yyvsp[(1) - (3)]), (yyvsp[(2) - (3)]), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 325:
#line 2141 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_NUMERIC_SCALAR);
  ;}
    break;

  case 326:
#line 2144 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_NUMERIC_SCALAR);
  ;}
    break;

  case 327:
#line 2147 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_STRING_SCALAR);
  ;}
    break;

  case 328:
#line 2150 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_MAGIC_SCALAR);
  ;}
    break;

  case 329:
#line 2153 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_MAGIC_SCALAR);
  ;}
    break;

  case 330:
#line 2156 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_MAGIC_SCALAR);
  ;}
    break;

  case 331:
#line 2159 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_MAGIC_SCALAR);
  ;}
    break;

  case 332:
#line 2162 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_MAGIC_SCALAR);
  ;}
    break;

  case 333:
#line 2165 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_MAGIC_SCALAR);
  ;}
    break;

  case 334:
#line 2168 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_MAGIC_SCALAR);
  ;}
    break;

  case 335:
#line 2171 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_MAGIC_SCALAR);
  ;}
    break;

  case 336:
#line 2174 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_HEREDOC);
  ;}
    break;

  case 339:
#line 2182 "parser.y"
    {
    NMORE((yyvsp[(3) - (3)]), (yyvsp[(1) - (3)]));
    (yyval) = (yyvsp[(3) - (3)]);
  ;}
    break;

  case 340:
#line 2186 "parser.y"
    {
    NMORE((yyvsp[(2) - (2)]), (yyvsp[(1) - (2)]));
    (yyval) = (yyvsp[(2) - (2)]);
  ;}
    break;

  case 341:
#line 2190 "parser.y"
    {
    (yyval) = NNEW(n_UNARY_PREFIX_EXPRESSION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 342:
#line 2195 "parser.y"
    {
    (yyval) = NNEW(n_UNARY_PREFIX_EXPRESSION);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_OPERATOR));
    (yyval)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 343:
#line 2200 "parser.y"
    {
    NTYPE((yyvsp[(1) - (4)]), n_ARRAY_LITERAL);
    (yyvsp[(1) - (4)])->appendChild(NEXPAND((yyvsp[(2) - (4)]), (yyvsp[(3) - (4)]), (yyvsp[(4) - (4)])));
    (yyval) = (yyvsp[(1) - (4)]);
  ;}
    break;

  case 344:
#line 2205 "parser.y"
    {
    NTYPE((yyvsp[(1) - (3)]), n_ARRAY_LITERAL);
    (yyvsp[(1) - (3)])->appendChild(NEXPAND((yyvsp[(1) - (3)]), (yyvsp[(2) - (3)]), (yyvsp[(3) - (3)])));
    (yyval) = (yyvsp[(1) - (3)]);
  ;}
    break;

  case 346:
#line 2214 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_STATIC_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(3) - (3)]), n_STRING));
  ;}
    break;

  case 350:
#line 2225 "parser.y"
    {
    (yyval) = NMORE((yyvsp[(3) - (3)]), (yyvsp[(1) - (3)]));
  ;}
    break;

  case 351:
#line 2228 "parser.y"
    {
    (yyval) = NMORE((yyvsp[(2) - (2)]), (yyvsp[(1) - (2)]));
  ;}
    break;

  case 353:
#line 2235 "parser.y"
    {
    (yyval) = NNEW(n_ARRAY_VALUE_LIST);
  ;}
    break;

  case 354:
#line 2238 "parser.y"
    {
    (yyval) = NMORE((yyvsp[(1) - (2)]), (yyvsp[(2) - (2)]));
  ;}
    break;

  case 355:
#line 2244 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 357:
#line 2255 "parser.y"
    {
    (yyval) = NNEW(n_ARRAY_VALUE);
    (yyval)->appendChild((yyvsp[(3) - (5)]));
    (yyval)->appendChild((yyvsp[(5) - (5)]));

    (yyval) = (yyvsp[(1) - (5)])->appendChild((yyval));
  ;}
    break;

  case 358:
#line 2262 "parser.y"
    {
    (yyval) = NNEW(n_ARRAY_VALUE);
    (yyval)->appendChild(NNEW(n_EMPTY));
    (yyval)->appendChild((yyvsp[(3) - (3)]));

    (yyval) = (yyvsp[(1) - (3)])->appendChild((yyval));
  ;}
    break;

  case 359:
#line 2269 "parser.y"
    {
    (yyval) = NNEW(n_ARRAY_VALUE);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild((yyvsp[(3) - (3)]));

    (yyval) = NNEW(n_ARRAY_VALUE_LIST)->appendChild((yyval));
  ;}
    break;

  case 360:
#line 2276 "parser.y"
    {
    (yyval) = NNEW(n_ARRAY_VALUE);
    (yyval)->appendChild(NNEW(n_EMPTY));
    (yyval)->appendChild((yyvsp[(1) - (1)]));

    (yyval) = NNEW(n_ARRAY_VALUE_LIST)->appendChild((yyval));
  ;}
    break;

  case 366:
#line 2306 "parser.y"
    {
    (yyval) = NNEW(n_OBJECT_PROPERTY_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (5)]));
    (yyval)->appendChild((yyvsp[(3) - (5)]));

    if ((yyvsp[(4) - (5)])->type != n_EMPTY) {
      (yyval) = NNEW(n_METHOD_CALL)->appendChild((yyval));
      (yyval)->appendChild((yyvsp[(4) - (5)]));
    }

    for (xhpast::node_list_t::iterator ii = (yyvsp[(5) - (5)])->children.begin();
      ii != (yyvsp[(5) - (5)])->children.end();
      ++ii) {

      if ((*ii)->type == n_CALL_PARAMETER_LIST) {
        (yyval) = NNEW(n_METHOD_CALL)->appendChild((yyval));
        (yyval)->appendChild((*ii));
      } else {
        (yyval) = NNEW(n_OBJECT_PROPERTY_ACCESS)->appendChild((yyval));
        (yyval)->appendChild((*ii));
      }
    }
  ;}
    break;

  case 368:
#line 2333 "parser.y"
    {
    (yyval) = (yyvsp[(1) - (2)])->appendChildren((yyvsp[(2) - (2)]));
  ;}
    break;

  case 369:
#line 2336 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 370:
#line 2342 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
    (yyval)->appendChild((yyvsp[(2) - (3)]));
    if ((yyvsp[(3) - (3)])->type != n_EMPTY) {
      (yyval)->appendChild((yyvsp[(3) - (3)]));
    }
  ;}
    break;

  case 371:
#line 2352 "parser.y"
    {
    (yyval) = NNEW(n_INDEX_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild((yyvsp[(3) - (4)]));
    NMORE((yyval), (yyvsp[(4) - (4)]));
  ;}
    break;

  case 372:
#line 2358 "parser.y"
    {
    (yyval) = NNEW(n_INDEX_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild((yyvsp[(3) - (4)]));
    NMORE((yyval), (yyvsp[(4) - (4)]));
  ;}
    break;

  case 373:
#line 2367 "parser.y"
    {
    (yyval) = NEXPAND((yyvsp[(1) - (3)]), (yyvsp[(2) - (3)]), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 376:
#line 2375 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 378:
#line 2382 "parser.y"
    {
    xhpast::Node *last = (yyvsp[(1) - (2)]);
    NMORE((yyvsp[(1) - (2)]), (yyvsp[(2) - (2)]));
    while (last->firstChild() &&
           last->firstChild()->type == n_VARIABLE_VARIABLE) {
      NMORE(last, (yyvsp[(2) - (2)]));
      last = last->firstChild();
    }
    last->appendChild((yyvsp[(2) - (2)]));

    (yyval) = (yyvsp[(1) - (2)]);
  ;}
    break;

  case 379:
#line 2397 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_STATIC_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 380:
#line 2402 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_STATIC_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 382:
#line 2414 "parser.y"
    {
    (yyval) = NNEW(n_INDEX_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild((yyvsp[(3) - (4)]));
    NMORE((yyval), (yyvsp[(4) - (4)]));
  ;}
    break;

  case 383:
#line 2420 "parser.y"
    {
    (yyval) = NNEW(n_INDEX_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild((yyvsp[(3) - (4)]));
    NMORE((yyval), (yyvsp[(4) - (4)]));
  ;}
    break;

  case 388:
#line 2436 "parser.y"
    {
    (yyval) = NEXPAND((yyvsp[(1) - (3)]), (yyvsp[(2) - (3)]), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 389:
#line 2439 "parser.y"
    {
    xhpast::Node *last = (yyvsp[(1) - (2)]);
    NMORE((yyvsp[(1) - (2)]), (yyvsp[(2) - (2)]));
    while (last->firstChild() &&
           last->firstChild()->type == n_VARIABLE_VARIABLE) {
      NMORE(last, (yyvsp[(2) - (2)]));
      last = last->firstChild();
    }
    last->appendChild((yyvsp[(2) - (2)]));

    (yyval) = (yyvsp[(1) - (2)]);
  ;}
    break;

  case 391:
#line 2455 "parser.y"
    {
    (yyval) = NNEW(n_INDEX_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild((yyvsp[(3) - (4)]));
    NMORE((yyval), (yyvsp[(4) - (4)]));
  ;}
    break;

  case 392:
#line 2461 "parser.y"
    {
    (yyval) = NNEW(n_INDEX_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild((yyvsp[(3) - (4)]));
    NMORE((yyval), (yyvsp[(4) - (4)]));
  ;}
    break;

  case 394:
#line 2471 "parser.y"
    {
    NTYPE((yyvsp[(1) - (1)]), n_VARIABLE);
  ;}
    break;

  case 395:
#line 2474 "parser.y"
    {
    NSPAN((yyvsp[(1) - (4)]), n_VARIABLE_EXPRESSION, (yyvsp[(4) - (4)]));
    (yyvsp[(1) - (4)])->appendChild((yyvsp[(3) - (4)]));
    (yyval) = (yyvsp[(1) - (4)]);
  ;}
    break;

  case 396:
#line 2482 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 397:
#line 2485 "parser.y"
    {
    (yyval) = (yyvsp[(1) - (1)]);
  ;}
    break;

  case 400:
#line 2496 "parser.y"
    {
    (yyval) = NNEW(n_INDEX_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild((yyvsp[(3) - (4)]));
    NMORE((yyval), (yyvsp[(4) - (4)]));
  ;}
    break;

  case 401:
#line 2502 "parser.y"
    {
    (yyval) = NNEW(n_INDEX_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild((yyvsp[(3) - (4)]));
    NMORE((yyval), (yyvsp[(4) - (4)]));
  ;}
    break;

  case 403:
#line 2512 "parser.y"
    {
    NTYPE((yyvsp[(1) - (1)]), n_STRING);
    (yyval) = (yyvsp[(1) - (1)]);
  ;}
    break;

  case 404:
#line 2516 "parser.y"
    {
  (yyval) = NEXPAND((yyvsp[(1) - (3)]), (yyvsp[(2) - (3)]), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 405:
#line 2522 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (1)]), n_VARIABLE_VARIABLE);
  ;}
    break;

  case 406:
#line 2525 "parser.y"
    {
    (yyvsp[(2) - (2)]) = NTYPE((yyvsp[(2) - (2)]), n_VARIABLE_VARIABLE);

    xhpast::Node *last = (yyvsp[(1) - (2)]);
    while (last->firstChild() &&
           last->firstChild()->type == n_VARIABLE_VARIABLE) {
      last = last->firstChild();
    }
    last->appendChild((yyvsp[(2) - (2)]));

    (yyval) = (yyvsp[(1) - (2)]);
  ;}
    break;

  case 407:
#line 2540 "parser.y"
    {
    (yyval) = (yyvsp[(1) - (3)])->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 408:
#line 2543 "parser.y"
    {
    (yyval) = NNEW(n_ASSIGNMENT_LIST);
    (yyval)->appendChild((yyvsp[(1) - (1)]));
  ;}
    break;

  case 410:
#line 2551 "parser.y"
    {
    (yyval) = NNEW(n_LIST);
    (yyval)->appendChild(NEXPAND((yyvsp[(2) - (4)]), (yyvsp[(3) - (4)]), (yyvsp[(4) - (4)])));
  ;}
    break;

  case 411:
#line 2555 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
  ;}
    break;

  case 412:
#line 2561 "parser.y"
    {
    (yyval) = NNEW(n_ARRAY_VALUE_LIST);
  ;}
    break;

  case 413:
#line 2564 "parser.y"
    {
    (yyval) = NMORE((yyvsp[(1) - (2)]), (yyvsp[(2) - (2)]));
  ;}
    break;

  case 414:
#line 2570 "parser.y"
    {
    (yyval) = NNEW(n_ARRAY_VALUE);
    (yyval)->appendChild((yyvsp[(3) - (5)]));
    (yyval)->appendChild((yyvsp[(5) - (5)]));

    (yyval) = (yyvsp[(1) - (5)])->appendChild((yyval));
  ;}
    break;

  case 415:
#line 2577 "parser.y"
    {
    (yyval) = NNEW(n_ARRAY_VALUE);
    (yyval)->appendChild(NNEW(n_EMPTY));
    (yyval)->appendChild((yyvsp[(3) - (3)]));

    (yyval) = (yyvsp[(1) - (3)])->appendChild((yyval));
  ;}
    break;

  case 416:
#line 2584 "parser.y"
    {
    (yyval) = NNEW(n_ARRAY_VALUE);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild((yyvsp[(3) - (3)]));

    (yyval) = NNEW(n_ARRAY_VALUE_LIST)->appendChild((yyval));
  ;}
    break;

  case 417:
#line 2591 "parser.y"
    {
    (yyval) = NNEW(n_ARRAY_VALUE);
    (yyval)->appendChild(NNEW(n_EMPTY));
    (yyval)->appendChild((yyvsp[(1) - (1)]));

    (yyval) = NNEW(n_ARRAY_VALUE_LIST)->appendChild((yyval));
  ;}
    break;

  case 418:
#line 2598 "parser.y"
    {
    (yyval) = NNEW(n_ARRAY_VALUE);
    (yyval)->appendChild((yyvsp[(3) - (6)]));
    (yyval)->appendChild(NTYPE((yyvsp[(5) - (6)]), n_VARIABLE_REFERENCE)->appendChild((yyvsp[(6) - (6)])));

    (yyval) = (yyvsp[(1) - (6)])->appendChild((yyval));
  ;}
    break;

  case 419:
#line 2605 "parser.y"
    {
    (yyval) = NNEW(n_ARRAY_VALUE);
    (yyval)->appendChild(NNEW(n_EMPTY));
    (yyval)->appendChild(NTYPE((yyvsp[(3) - (4)]), n_VARIABLE_REFERENCE)->appendChild((yyvsp[(4) - (4)])));

    (yyval) = (yyvsp[(1) - (4)])->appendChild((yyval));
  ;}
    break;

  case 420:
#line 2612 "parser.y"
    {
    (yyval) = NNEW(n_ARRAY_VALUE);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild(NTYPE((yyvsp[(3) - (4)]), n_VARIABLE_REFERENCE)->appendChild((yyvsp[(4) - (4)])));

    (yyval) = NNEW(n_ARRAY_VALUE_LIST)->appendChild((yyval));
  ;}
    break;

  case 421:
#line 2619 "parser.y"
    {
    (yyval) = NNEW(n_ARRAY_VALUE);
    (yyval)->appendChild(NNEW(n_EMPTY));
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (2)]), n_VARIABLE_REFERENCE)->appendChild((yyvsp[(2) - (2)])));

    (yyval) = NNEW(n_ARRAY_VALUE_LIST)->appendChild((yyval));
  ;}
    break;

  case 422:
#line 2629 "parser.y"
    {
    NTYPE((yyvsp[(1) - (4)]), n_SYMBOL_NAME);

    NSPAN((yyvsp[(2) - (4)]), n_CALL_PARAMETER_LIST, (yyvsp[(4) - (4)]));
    (yyvsp[(2) - (4)])->appendChildren((yyvsp[(3) - (4)]));

    (yyval) = NNEW(n_FUNCTION_CALL);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild((yyvsp[(2) - (4)]));
  ;}
    break;

  case 423:
#line 2639 "parser.y"
    {
    NTYPE((yyvsp[(1) - (4)]), n_SYMBOL_NAME);

    NSPAN((yyvsp[(2) - (4)]), n_CALL_PARAMETER_LIST, (yyvsp[(4) - (4)]));
    (yyvsp[(2) - (4)])->appendChild((yyvsp[(3) - (4)]));

    (yyval) = NNEW(n_FUNCTION_CALL);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild((yyvsp[(2) - (4)]));
  ;}
    break;

  case 424:
#line 2649 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (2)]), n_INCLUDE_FILE)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 425:
#line 2652 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (2)]), n_INCLUDE_FILE)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 426:
#line 2655 "parser.y"
    {
    NTYPE((yyvsp[(1) - (4)]), n_SYMBOL_NAME);

    NSPAN((yyvsp[(2) - (4)]), n_CALL_PARAMETER_LIST, (yyvsp[(4) - (4)]));
    (yyvsp[(2) - (4)])->appendChild((yyvsp[(3) - (4)]));

    (yyval) = NNEW(n_FUNCTION_CALL);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild((yyvsp[(2) - (4)]));
  ;}
    break;

  case 427:
#line 2665 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (2)]), n_INCLUDE_FILE)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 428:
#line 2668 "parser.y"
    {
    (yyval) = NTYPE((yyvsp[(1) - (2)]), n_INCLUDE_FILE)->appendChild((yyvsp[(2) - (2)]));
  ;}
    break;

  case 429:
#line 2674 "parser.y"
    {
    (yyval) = NNEW(n_EMPTY);
    (yyval)->appendChild((yyvsp[(1) - (1)]));
  ;}
    break;

  case 430:
#line 2678 "parser.y"
    {
    (yyval) = (yyvsp[(1) - (3)])->appendChild((yyvsp[(3) - (3)]));
  ;}
    break;

  case 431:
#line 2684 "parser.y"
    {
    NSPAN((yyvsp[(1) - (3)]), n_PARENTHETICAL_EXPRESSION, (yyvsp[(3) - (3)]));
    (yyvsp[(1) - (3)])->appendChild((yyvsp[(2) - (3)]));
    (yyval) = (yyvsp[(1) - (3)]);
  ;}
    break;

  case 432:
#line 2689 "parser.y"
    {
    (yyval) = NEXPAND((yyvsp[(1) - (3)]), (yyvsp[(2) - (3)]), (yyvsp[(3) - (3)]));
  ;}
    break;

  case 433:
#line 2695 "parser.y"
    {
    (yyval) = NNEW(n_INDEX_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild((yyvsp[(3) - (4)]));
    NMORE((yyval), (yyvsp[(4) - (4)]));
  ;}
    break;

  case 434:
#line 2701 "parser.y"
    {
    (yyval) = NNEW(n_INDEX_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild((yyvsp[(3) - (4)]));
    NMORE((yyval), (yyvsp[(4) - (4)]));
  ;}
    break;

  case 435:
#line 2707 "parser.y"
    {
    (yyval) = NNEW(n_INDEX_ACCESS);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (4)]), n_STRING_SCALAR));
    (yyval)->appendChild((yyvsp[(3) - (4)]));
    NMORE((yyval), (yyvsp[(4) - (4)]));
  ;}
    break;

  case 436:
#line 2713 "parser.y"
    {
    (yyval) = NNEW(n_INDEX_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (4)]));
    (yyval)->appendChild((yyvsp[(3) - (4)]));
    NMORE((yyval), (yyvsp[(4) - (4)]));
  ;}
    break;

  case 437:
#line 2719 "parser.y"
    {
    (yyval) = NNEW(n_INDEX_ACCESS);
    (yyval)->appendChild(NTYPE((yyvsp[(1) - (4)]), n_STRING));
    (yyval)->appendChild((yyvsp[(3) - (4)]));
    NMORE((yyval), (yyvsp[(4) - (4)]));
  ;}
    break;

  case 438:
#line 2728 "parser.y"
    {
    NTYPE((yyvsp[(1) - (4)]), n_ARRAY_LITERAL);
    (yyvsp[(1) - (4)])->appendChild(NEXPAND((yyvsp[(2) - (4)]), (yyvsp[(3) - (4)]), (yyvsp[(4) - (4)])));
    (yyval) = (yyvsp[(1) - (4)]);
  ;}
    break;

  case 439:
#line 2733 "parser.y"
    {
    NTYPE((yyvsp[(1) - (3)]), n_ARRAY_LITERAL);
    (yyvsp[(1) - (3)])->appendChild(NEXPAND((yyvsp[(1) - (3)]), (yyvsp[(2) - (3)]), (yyvsp[(3) - (3)])));
    (yyval) = (yyvsp[(1) - (3)]);
  ;}
    break;

  case 440:
#line 2741 "parser.y"
    {
    NTYPE((yyvsp[(1) - (3)]), n_NEW);
    (yyvsp[(1) - (3)])->appendChild((yyvsp[(2) - (3)]));
    (yyvsp[(1) - (3)])->appendChild((yyvsp[(3) - (3)]));
    (yyval) = (yyvsp[(1) - (3)]);
  ;}
    break;

  case 441:
#line 2748 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_DECLARATION);
    (yyval)->appendChild(NNEW(n_EMPTY));
    (yyval)->appendChild(NNEW(n_EMPTY));
    (yyval)->appendChild((yyvsp[(4) - (8)]));
    (yyval)->appendChild((yyvsp[(5) - (8)]));
    (yyval)->appendChild(NEXPAND((yyvsp[(6) - (8)]), (yyvsp[(7) - (8)]), (yyvsp[(8) - (8)])));
    NMORE((yyval), (yyvsp[(8) - (8)]));

    NTYPE((yyvsp[(1) - (8)]), n_NEW);
    (yyvsp[(1) - (8)])->appendChild((yyval));
    (yyvsp[(1) - (8)])->appendChild((yyvsp[(3) - (8)]));
    (yyval) = (yyvsp[(1) - (8)]);
  ;}
    break;

  case 442:
#line 2765 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_STATIC_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(3) - (3)]), n_STRING));
  ;}
    break;

  case 443:
#line 2770 "parser.y"
    {
    (yyval) = NNEW(n_CLASS_STATIC_ACCESS);
    (yyval)->appendChild((yyvsp[(1) - (3)]));
    (yyval)->appendChild(NTYPE((yyvsp[(3) - (3)]), n_STRING));
  ;}
    break;


/* Line 1267 of yacc.c.  */
#line 7463 "parser.yacc.cpp"
      default: break;
    }
  YY_SYMBOL_PRINT ("-> $$ =", yyr1[yyn], &yyval, &yyloc);

  YYPOPSTACK (yylen);
  yylen = 0;
  YY_STACK_PRINT (yyss, yyssp);

  *++yyvsp = yyval;


  /* Now `shift' the result of the reduction.  Determine what state
     that goes to, based on the state we popped back to and the rule
     number reduced by.  */

  yyn = yyr1[yyn];

  yystate = yypgoto[yyn - YYNTOKENS] + *yyssp;
  if (0 <= yystate && yystate <= YYLAST && yycheck[yystate] == *yyssp)
    yystate = yytable[yystate];
  else
    yystate = yydefgoto[yyn - YYNTOKENS];

  goto yynewstate;


/*------------------------------------.
| yyerrlab -- here on detecting error |
`------------------------------------*/
yyerrlab:
  /* If not already recovering from an error, report this error.  */
  if (!yyerrstatus)
    {
      ++yynerrs;
#if ! YYERROR_VERBOSE
      yyerror (yyscanner, root, YY_("syntax error"));
#else
      {
	YYSIZE_T yysize = yysyntax_error (0, yystate, yychar);
	if (yymsg_alloc < yysize && yymsg_alloc < YYSTACK_ALLOC_MAXIMUM)
	  {
	    YYSIZE_T yyalloc = 2 * yysize;
	    if (! (yysize <= yyalloc && yyalloc <= YYSTACK_ALLOC_MAXIMUM))
	      yyalloc = YYSTACK_ALLOC_MAXIMUM;
	    if (yymsg != yymsgbuf)
	      YYSTACK_FREE (yymsg);
	    yymsg = (char *) YYSTACK_ALLOC (yyalloc);
	    if (yymsg)
	      yymsg_alloc = yyalloc;
	    else
	      {
		yymsg = yymsgbuf;
		yymsg_alloc = sizeof yymsgbuf;
	      }
	  }

	if (0 < yysize && yysize <= yymsg_alloc)
	  {
	    (void) yysyntax_error (yymsg, yystate, yychar);
	    yyerror (yyscanner, root, yymsg);
	  }
	else
	  {
	    yyerror (yyscanner, root, YY_("syntax error"));
	    if (yysize != 0)
	      goto yyexhaustedlab;
	  }
      }
#endif
    }



  if (yyerrstatus == 3)
    {
      /* If just tried and failed to reuse look-ahead token after an
	 error, discard it.  */

      if (yychar <= YYEOF)
	{
	  /* Return failure if at end of input.  */
	  if (yychar == YYEOF)
	    YYABORT;
	}
      else
	{
	  yydestruct ("Error: discarding",
		      yytoken, &yylval, yyscanner, root);
	  yychar = YYEMPTY;
	}
    }

  /* Else will try to reuse look-ahead token after shifting the error
     token.  */
  goto yyerrlab1;


/*---------------------------------------------------.
| yyerrorlab -- error raised explicitly by YYERROR.  |
`---------------------------------------------------*/
yyerrorlab:

  /* Pacify compilers like GCC when the user code never invokes
     YYERROR and the label yyerrorlab therefore never appears in user
     code.  */
  if (/*CONSTCOND*/ 0)
     goto yyerrorlab;

  /* Do not reclaim the symbols of the rule which action triggered
     this YYERROR.  */
  YYPOPSTACK (yylen);
  yylen = 0;
  YY_STACK_PRINT (yyss, yyssp);
  yystate = *yyssp;
  goto yyerrlab1;


/*-------------------------------------------------------------.
| yyerrlab1 -- common code for both syntax error and YYERROR.  |
`-------------------------------------------------------------*/
yyerrlab1:
  yyerrstatus = 3;	/* Each real token shifted decrements this.  */

  for (;;)
    {
      yyn = yypact[yystate];
      if (yyn != YYPACT_NINF)
	{
	  yyn += YYTERROR;
	  if (0 <= yyn && yyn <= YYLAST && yycheck[yyn] == YYTERROR)
	    {
	      yyn = yytable[yyn];
	      if (0 < yyn)
		break;
	    }
	}

      /* Pop the current state because it cannot handle the error token.  */
      if (yyssp == yyss)
	YYABORT;


      yydestruct ("Error: popping",
		  yystos[yystate], yyvsp, yyscanner, root);
      YYPOPSTACK (1);
      yystate = *yyssp;
      YY_STACK_PRINT (yyss, yyssp);
    }

  if (yyn == YYFINAL)
    YYACCEPT;

  *++yyvsp = yylval;


  /* Shift the error token.  */
  YY_SYMBOL_PRINT ("Shifting", yystos[yyn], yyvsp, yylsp);

  yystate = yyn;
  goto yynewstate;


/*-------------------------------------.
| yyacceptlab -- YYACCEPT comes here.  |
`-------------------------------------*/
yyacceptlab:
  yyresult = 0;
  goto yyreturn;

/*-----------------------------------.
| yyabortlab -- YYABORT comes here.  |
`-----------------------------------*/
yyabortlab:
  yyresult = 1;
  goto yyreturn;

#ifndef yyoverflow
/*-------------------------------------------------.
| yyexhaustedlab -- memory exhaustion comes here.  |
`-------------------------------------------------*/
yyexhaustedlab:
  yyerror (yyscanner, root, YY_("memory exhausted"));
  yyresult = 2;
  /* Fall through.  */
#endif

yyreturn:
  if (yychar != YYEOF && yychar != YYEMPTY)
     yydestruct ("Cleanup: discarding lookahead",
		 yytoken, &yylval, yyscanner, root);
  /* Do not reclaim the symbols of the rule which action triggered
     this YYABORT or YYACCEPT.  */
  YYPOPSTACK (yylen);
  YY_STACK_PRINT (yyss, yyssp);
  while (yyssp != yyss)
    {
      yydestruct ("Cleanup: popping",
		  yystos[*yyssp], yyvsp, yyscanner, root);
      YYPOPSTACK (1);
    }
#ifndef yyoverflow
  if (yyss != yyssa)
    YYSTACK_FREE (yyss);
#endif
#if YYERROR_VERBOSE
  if (yymsg != yymsgbuf)
    YYSTACK_FREE (yymsg);
#endif
  /* Make sure YYID is used.  */
  return YYID (yyresult);
}


#line 2777 "parser.y"


const char* yytokname(int tok) {
  if (tok < 255) {
    return NULL;
  }
  return yytname[YYTRANSLATE(tok)];
}

/* @generated */
