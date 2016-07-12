#!/usr/bin/env python2
import sys
import time
import select
import curses
from curses import wrapper

entities = []
grid = []

class Wall:
    def collide(self, ball):
        return False

class Block:
    killed = 0
    total = 0

    def __init__(self, x, y, w, h, c):
        self.x = x
        self.y = y
        self.w = w
        self.h = h
        self.fmt = curses.A_BOLD | curses.color_pair(c)
        self.alive = True
        for i in range(self.x, self.x + self.w):
            for j in range(self.y, self.y + self.h):
                grid[j + 1][i + 1] = self
        Block.total += 1

    def collide(self, ball):
        self.alive = False
        for i in range(self.x, self.x + self.w):
            for j in range(self.y, self.y + self.h):
                grid[j + 1][i + 1] = None
        Block.killed += 1
        return False

    def tick(self, win):
        if self.alive:
            for i in range(self.x, self.x + self.w):
                for j in range(self.y, self.y + self.h):
                    win.addch(j, i, curses.ACS_BLOCK, self.fmt)
        return self.alive

class Ball:
    alive = False
    killed = 0

    def __init__(self, x, y, vx, vy):
        self.x = x
        self.y = y
        self.vx = vx
        self.vy = vy
        Ball.alive = True

    def collide(self, ball):
        return True

    def encounter(self, dx, dy):
        ent = grid[self.y + dy + 1][self.x + dx + 1]
        if ent and not ent.collide(self):
            self.vx -= 2 * dx
            self.vy -= 2 * dy
        return ent

    def tick(self, win):
        while self.y < ship.y:
            if self.encounter((self.vx + self.vy) / 2, (self.vy - self.vx) / 2):
                continue
            if self.encounter((self.vx - self.vy) / 2, (self.vy + self.vx) / 2):
                continue
            if self.encounter(self.vx, self.vy):
                continue
            break
        self.x += self.vx
        self.y += self.vy
        try:
            win.addch(self.y, self.x, 'O')
        except curses.error:
            Ball.alive = False
            Ball.killed += 1
        return Ball.alive

class Ship:
    def __init__(self, x, y):
        self.x = x
        self.y = y
        self.hw = 10
        self.v = 4
        self.last = 1
        self.update()

    def update(self):
        grid[self.y + 1] = (
            [ None ] * (self.x - self.hw + 1) +
            [ self ] * (self.hw * 2 + 1) +
            [ None ] * (width - self.x - self.hw)
        )

    def collide(self, ball):
        ball.vy = -1
        if ball.x > self.x + self.hw / 2:
            ball.vx = 1
        elif ball.x < self.x - self.hw / 2:
            ball.vx = -1
        return True

    def shift(self, i):
        self.last = i
        self.x += self.v * i
        if self.x - self.hw < 0:
            self.x = self.hw
        elif self.x + self.hw >= width:
            self.x = width - self.hw - 1
        self.update()

    def spawn(self):
        if not Ball.alive:
            entities.append(Ball(self.x, self.y - 1, self.last, -1))

    def tick(self, win):
        if not Ball.alive:
            win.addch(self.y - 1, self.x, 'O')
        win.addch(self.y, self.x - self.hw, curses.ACS_LTEE)
        for i in range(-self.hw + 1, self.hw):
            win.addch(curses.ACS_HLINE)
        win.addch(curses.ACS_RTEE)
        return True

class PowerOverwhelmingException(Exception):
    pass

def main(stdscr):
    global height, width, ship

    for i in range(1, 8):
        curses.init_pair(i, i, 0)
    curses.curs_set(0)
    curses.raw()

    height, width = stdscr.getmaxyx()

    if height < 15 or width < 30:
        raise PowerOverwhelmingException(
            "Your computer is not powerful enough to run 'arc anoid'. "
            "It must support at least 30 columns and 15 rows of next-gen "
            "full-color 3D graphics.")

    status = curses.newwin(1, width, 0, 0)
    height -= 1
    game = curses.newwin(height, width, 1, 0)
    game.nodelay(1)
    game.keypad(1)

    grid[:] = [ [ None for x in range(width + 2) ] for y in range(height + 2) ]
    wall = Wall()
    for x in range(width + 2):
        grid[0][x] = wall
    for y in range(height + 2):
        grid[y][0] = grid[y][-1] = wall
    ship = Ship(width / 2, height - 5)
    entities.append(ship)

    colors = [ 1, 3, 2, 6, 4, 5 ]
    h = height / 10
    for x in range(1, width / 7 - 1):
        for y in range(1, 7):
            entities.append(Block(x * 7,
                                  y * h + x / 2 % 2,
                                  7,
                                  h,
                                  colors[y - 1]))

    while True:
        while select.select([ sys.stdin ], [], [], 0)[0]:
            key = game.getch()
            if key == curses.KEY_LEFT or key == ord('a') or key == ord('A'):
                ship.shift(-1)
            elif key == curses.KEY_RIGHT or key == ord('d') or key == ord('D'):
                ship.shift(1)
            elif key == ord(' '):
                ship.spawn()
            elif key == 0x1b or key == 3 or key == ord('q') or key == ord('Q'):
                return

        game.resize(height, width)
        game.erase()
        entities[:] = [ ent for ent in entities if ent.tick(game) ]

        status.hline(0, 0, curses.ACS_HLINE, width)
        status.addch(0, 2, curses.ACS_RTEE)
        status.addstr(' SCORE: ', curses.A_BOLD | curses.color_pair(4))
        status.addstr('%s/%s ' % (Block.killed, Block.total), curses.A_BOLD)
        status.addch(curses.ACS_VLINE)
        status.addstr(' DEATHS: ', curses.A_BOLD | curses.color_pair(4))
        status.addstr('%s ' % Ball.killed, curses.A_BOLD)
        status.addch(curses.ACS_LTEE)

        if Block.killed == Block.total:
            message = ' A WINNER IS YOU!! '
            i = int(time.time() / 0.8)
            for x in range(width):
                for y in range(6):
                    game.addch(height / 2 + y - 3 + (x / 8 + i) % 2, x,
                               curses.ACS_BLOCK,
                               curses.A_BOLD | curses.color_pair(colors[y]))
            game.addstr(height / 2, (width - len(message)) / 2, message,
                           curses.A_BOLD | curses.color_pair(7))

        game.refresh()
        status.refresh()
        time.sleep(0.05)

try:
    curses.wrapper(main)
    print ('You destroyed %s blocks out of %s with %s deaths.' %
        (Block.killed, Block.total, Ball.killed))
except PowerOverwhelmingException as e:
    print (e)
