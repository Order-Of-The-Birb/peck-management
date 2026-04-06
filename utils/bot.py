import logging, discord
from sys import modules as sysmodules
from threading import Lock
from importlib import reload
from discord.ext import commands, tasks
from datetime import datetime, UTC, timedelta
from typing import TYPE_CHECKING, TypedDict, Literal, TypeAlias
from os import getenv
if __name__ == "__main__":
	from os import path
	from sys import path as sys_path
	sys_path.append(path.abspath(path.join(path.dirname(__file__), '..')))
from modules.db import UserRepository
from utils.wt import Squadron
if TYPE_CHECKING:
	from modules.newsAPI import NewsAPI
	from asyncio import AbstractEventLoop
logger = logging.getLogger(__name__)

class ChannelIDs(TypedDict):
	logs: int
	spam: int
	announcements: int
	sqb: int
	wtNews: int
	score: int
	propaganda: int

class RoleIDs(TypedDict):
	member: int
	ping: int
	oldTimer: int
	retired: int
	majorNews: int
	eventNews: int
	applicant: int

class CategoryIDs(TypedDict):
	clips: int
	squadVC: int

class GenericTimeout:
	cleanupTask:tasks.Loop
	expiry:timedelta
	def __init__(self, bot:'Bot', timeout_after:int=3, expires_after:timedelta = timedelta(minutes=5)):
		self._wait_until_ready = bot.wait_until_ready
		self.timeoutCount:int = timeout_after
		self.cooldowns:dict[int, list[datetime]] = {}
		self.expiry	= expires_after

		self.cleanupTask = self.cleanup
	def isTimedOut(self, user_id:int) -> bool:
		return len(self.cooldowns.get(user_id, [])) >= self.timeoutCount
	def getOldest(self, user_id:int) -> datetime|None:
		tmp = self.cooldowns.get(user_id)
		return min(tmp) if tmp else None
	def add(self, user_id:int):
		now = datetime.now(UTC)
		self.cooldowns.setdefault(user_id, []).append(now)
	def getExpireTime(self, user_id: int) -> datetime | None:
		oldest = self.getOldest(user_id)
		if oldest is None:
			return None

		expire_time = oldest + self.expiry

		next_iter = self.cleanupTask.next_iteration
		if next_iter is not None:
			expire_time = max(expire_time, next_iter)

		return expire_time
	# region Integrated Loop
	@tasks.loop(minutes=1)
	async def cleanup(self):
		rn = datetime.now(UTC)
		for user, entries in list(self.cooldowns.items()):
			new = [e for e in entries if rn - e < self.expiry]
			if new:
				self.cooldowns[user] = new
			else:
				self.cooldowns.pop(user, None)
	@cleanup.before_loop
	async def _beforeCleanup(self):
		await self._wait_until_ready()
	def stop(self):
		if self.cleanupTask:
			self.cleanupTask.cancel()
	def run(self):
		if self.cleanupTask:
			self.cleanupTask.start()
	# endregion

class Bot(commands.Bot):
	ltsLock = Lock()
	class GaijinLogin:
		email:str
		password:str
		def __init__(self):
			self.email = getenv("WT_LOGIN_EMAIL")
			self.password = getenv("WT_LOGIN_PASS")
	class _PlanSQB:
		def __init__(self):
			self.announcementMessage:discord.Message|None = None
			self.announced:bool = False
			self.applicantInChannel:bool = False
			self.applicants:list[int] = []
			self.timeframe:tuple[datetime] = ()
			self.applicantJoinedChannel = False
	class _SquadVC:
		channels:list['SquadVCData'] = []
		checkDelay:int = 0
		class SquadVCData:
			_id:int
			last_seen_person_time:float
			creator:int|None
			created:float
			def __init__(self, _id:int, last_seen_person_time:float, creator:int|None, created:float):
				self._id = _id
				self.last_seen_person_time = last_seen_person_time
				self.creator = creator
				self.created = created
	def __init__(self, *args,**kwargs):
		self.debug:bool = kwargs.pop("debug")
		if not self.debug: # Live bot
			self.channelIDs:ChannelIDs = {
				"logs":1229136570498682900,
				"spam":1229136570498682900,
				"announcements":931263111992848395,
				"sqb":1353122797504827422,
				"wtNews":920311640161943552,
				"score":1125129523499896974,
				"propaganda":1066119609914232842,
				"auditLog": 1490460729994641408
			}
			self.roleIDs:RoleIDs = {
				"member": 919803983286128661,
				"ping": 1065769588849115248,
				"oldTimer": 917946204736860190,
				"retired": 983021494739292250,
				"majorNews": 1476690321051222119,
				"eventNews": 1489360745039921222,
				"applicant": 1184950087873466438
			}
			self.categoryIDs:CategoryIDs = {
				"clips": 1428798448102277263,
				"squadVC": 917850361019125821
			}
			self.commonRoles = [917852847482232852, 923683349611053106]
			self.peckServer = 917850361019125820
			self.sqbPlanCheckLimit = timedelta(minutes=30)
		else: # Debug bot
			self.channelIDs:ChannelIDs = {
				"logs":1453040931703095550,
				"spam":1453040954343821513,
				"announcements":1453040983934767277,
				"sqb":1453041059746676837,
				"wtNews":1453041016969101458,
				"score":1456770254972784780,
				"propaganda":1456788537755045931,
				"auditLog": ...
			}
			self.roleIDs:RoleIDs = {
				"member": 1073297853587398696,
				"ping": 1456660597616803956,
				"oldTimer": 1454228913742938112,
				"retired": 1456971952165093467,
				"majorNews": 1476690625612222647,
				"eventNews": 1489360453233541170,
				"applicant": 1477308945759735900
			}
			self.categoryIDs:CategoryIDs = {
				"clips": 1454228135280119982,
				"squadVC": 1453040903626293338
			}
			self.commonRoles = [1124647932696743948]
			self.peckServer = 685948316541779974
			self.sqbPlanCheckLimit = timedelta(minutes=2)
		self.botIDs = [
			1005502691713237035, # Main Bot
			1007702877264941127 # Testing Bot
		]
		self.yoshinoID = 332030423913725953
		self.sqb_season_length = 2
		self.db = UserRepository()
		self.runningSince:datetime = datetime.now(UTC)
		self.iconURL ='https://cdn.discordapp.com/icons/917850361019125820/325129a273dac84e31f9aa5de51f1936.png?size=128'
		self.logLevel:int = kwargs.pop("logLevel")
		self.planSQB = self._PlanSQB()
		self.clipTimeout = GenericTimeout(self)
		self.aiTimeout = GenericTimeout(self)
		self.squadVC = self._SquadVC()
		self.runtime:AbstractEventLoop = kwargs.pop("runtime")
		self.newsAPI:'NewsAPI' = kwargs.pop("newsAPI", None)
		self.SQUADRON_TAG = "PECK"
		self.squadron = Squadron("Order Of The Birb")
		super().__init__(tree_cls=Tree, *args, **kwargs)
	def _reload_prefix(self, prefix: str):
		to_reload = [
			(name, module)
			for name, module in sysmodules.items()
			if module and name.startswith(prefix)
		]
		for name, module in to_reload:
			try:
				reload(module)
				logger.debug(f"Reloaded module: {name}")
			except Exception:
				logger.exception(f"Failed to reload module: {name}")
	async def reload_extension_with_deps(self,extension: str,*module_prefixes: str):
		"""Reload helper modules, then reload the extension."""
		if isinstance(module_prefixes, str):
			raise TypeError(f"__reload_deps__ must be a tuple, not str")
		if "modules.db" in module_prefixes:
			self.reload_db()
		for prefix in module_prefixes:
			if prefix != "modules.db":
				self._reload_prefix(prefix)
		await self.reload_extension(extension)
	async def memberHasRole(self, role_id:RoleIDs, member_id:int):
		return (self.get_guild(self.peckServer).get_member(member_id).get_role(self.roleIDs[role_id])) is not None
class Tree(discord.app_commands.CommandTree):
	async def on_error(self, interaction: discord.Interaction, error: Exception):
		if isinstance(error, discord.app_commands.CheckFailure):
			logger.warning(f"User {interaction.user.name} ({interaction.user.id}) tried running {interaction.command.name} without the required permissions")
			await interaction.response.send_message("You don't have the required Permissions", ephemeral=True, delete_after=5)
			return
		if not interaction.response.is_done():
			await interaction.response.send_message("Oops! Something went wrong.", ephemeral=True)
		raise error  # still log real bugs
def debug_only():
	async def predicate(interaction: discord.Interaction) -> bool:
		return interaction.client.debug
	return discord.app_commands.check(predicate)
def owner_only():
	async def predicate(interaction: discord.Interaction) -> bool:
		return interaction.user.id in [332030423913725953, 709449854371364895, interaction.client.yoshinoID]
	return discord.app_commands.check(predicate)
def officer_only():
	async def predicate(interaction: discord.Interaction) -> bool:
		if not interaction.guild: return False
		return (
			(interaction.user.guild_permissions.administrator and interaction.guild_id == 917850361019125820) or # PECK admin 
			interaction.user.id in [
				332030423913725953, # Maho Yoshino
				171971803625816075, # Tzatziki
				311555617032765451, # eevee
			]
		)
	return discord.app_commands.check(predicate)
def members_only():
	async def predicate(interaction: discord.Interaction) -> bool:
		if not interaction.guild: return False
		return any(role.id == interaction.client.roleIDs["member"] for role in interaction.user.roles)
	return discord.app_commands.check(predicate)